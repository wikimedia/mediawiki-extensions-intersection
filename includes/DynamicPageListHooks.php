<?php

use MediaWiki\MediaWikiServices;
use PageImages\PageImages;

class DynamicPageListHooks {

	/**
	 * Set up the <DynamicPageList> tag.
	 * @param Parser $parser
	 * @return bool true
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'DynamicPageList', 'DynamicPageListHooks::renderDynamicPageList' );
		return true;
	}

	/**
	 * The callback function for converting the input text to HTML output
	 * @param string $input
	 * @param array $args
	 * @param Parser $mwParser
	 * @return string
	 */
	public static function renderDynamicPageList( $input, $args, $mwParser ) {
		global $wgDLPmaxCategories, $wgDLPMaxResultCount, $wgDLPMaxCacheTime,
			$wgDLPAllowUnlimitedResults, $wgDLPAllowUnlimitedCategories;

		if ( $wgDLPMaxCacheTime !== false ) {
			$mwParser->getOutput()->updateCacheExpiry( $wgDLPMaxCacheTime );
		}

		$countSet = false;
		$count = 0;

		$startList = '<ul>';
		$endList = '</ul>';
		$startItem = '<li>';
		$endItem = '</li>';
		$inlineMode = false;

		$useGallery = false;
		$pageImagesEnabled = ExtensionRegistry::getInstance()->isLoaded( 'PageImages' );
		$galleryFileSize = false;
		$galleryFileName = true;
		$galleryImageHeight = 0;
		$galleryImageWidth = 0;
		$galleryNumbRows = 0;
		$galleryCaption = '';
		$gallery = null;

		$orderMethod = 'categoryadd';
		$order = 'descending';
		$redirects = 'exclude';
		$stable = $quality = 'include';
		$flaggedRevs = false;

		$namespaceFiltering = false;
		$namespaceIndex = 0;

		$offset = 0;

		$googleHack = false;

		$suppressErrors = false;
		$showNamespace = true;
		$addFirstCategoryDate = false;
		$ignoreSubpages = false;
		$dateFormat = '';
		$stripYear = false;

		$linkOptions = [];
		$categories = [];
		$excludeCategories = [];

		$parameters = explode( "\n", $input );

		$services = MediaWikiServices::getInstance();
		$parser = $services->getParserFactory()->create();
		$parser->setTitle( $mwParser->getTitle() );
		$poptions = new ParserOptions( $mwParser->getUser() );

		$contLang = $services->getContentLanguage();
		foreach ( $parameters as $parameter ) {
			$paramField = explode( '=', $parameter, 2 );
			if ( count( $paramField ) < 2 ) {
				continue;
			}
			$type = trim( $paramField[0] );
			$arg = trim( $paramField[1] );
			switch ( $type ) {
				case 'category':
					$title = Title::makeTitleSafe(
						NS_CATEGORY,
						$parser->transformMsg( $arg, $poptions, $mwParser->getTitle() )
					);
					if ( $title === null ) {
						break;
					}
					$categories[] = $title;
					break;
				case 'notcategory':
					$title = Title::makeTitleSafe(
						NS_CATEGORY,
						$parser->transformMsg( $arg, $poptions, $mwParser->getTitle() )
					);
					if ( $title === null ) {
						break;
					}
					$excludeCategories[] = $title;
					break;
				case 'namespace':
					$ns = $contLang->getNsIndex( $arg );
					if ( $ns !== null ) {
						$namespaceIndex = $ns;
						$namespaceFiltering = true;
					} else {
						// Note, since intval("some string") = 0
						// this considers pretty much anything
						// invalid here as the main namespace.
						// This was probably originally a bug,
						// but is now depended upon by people
						// writing things like namespace=main
						// so be careful when changing this code.
						$namespaceIndex = intval( $arg );
						if ( $namespaceIndex >= 0 ) {
							$namespaceFiltering = true;
						} else {
							$namespaceFiltering = false;
						}
					}
					break;
				case 'count':
					// ensure that $count is a number;
					$count = intval( $arg );
					$countSet = true;
					break;
				case 'offset':
					$offset = intval( $arg );
					break;
				case 'imagewidth':
					$galleryImageWidth = intval( $arg );
					break;
				case 'imageheight':
					$galleryImageHeight = intval( $arg );
					break;
				case 'imagesperrow':
					$galleryNumbRows = intval( $arg );
					break;
				case 'mode':
					switch ( $arg ) {
						case 'gallery':
							$useGallery = true;
							$gallery = ImageGalleryBase::factory();
							$gallery->setParser( $mwParser );
							$startList = '';
							$endList = '';
							$startItem = '';
							$endItem = '';
							break;
						case 'none':
							$startList = '';
							$endList = '';
							$startItem = '';
							$endItem = '<br />';
							$inlineMode = false;
							break;
						case 'ordered':
							$startList = '<ol>';
							$endList = '</ol>';
							$startItem = '<li>';
							$endItem = '</li>';
							$inlineMode = false;
							break;
						case 'inline':
							// aka comma separated list
							$startList = '';
							$endList = '';
							$startItem = '';
							$endItem = '';
							$inlineMode = true;
							break;
						case 'unordered':
						default:
							$startList = '<ul>';
							$endList = '</ul>';
							$startItem = '<li>';
							$endItem = '</li>';
							$inlineMode = false;
							break;
					}
					break;
				case 'gallerycaption':
					// Should perhaps actually parse caption instead
					// as links and what not in caption might be useful.
					$galleryCaption = $parser->transformMsg( $arg, $poptions, $mwParser->getTitle() );
					break;
				case 'galleryshowfilesize':
					switch ( $arg ) {
						case 'no':
						case 'false':
							$galleryFileSize = false;
							break;
						case 'true':
						default:
							$galleryFileSize = true;
					}
					break;
				case 'galleryshowfilename':
					switch ( $arg ) {
						case 'no':
						case 'false':
							$galleryFileName = false;
							break;
						case 'true':
						default:
							$galleryFileName = true;
							break;
					}
					break;
				case 'order':
					switch ( $arg ) {
						case 'ascending':
							$order = 'ascending';
							break;
						case 'descending':
						default:
							$order = 'descending';
							break;
					}
					break;
				case 'ordermethod':
					switch ( $arg ) {
						case 'lastedit':
							$orderMethod = 'lastedit';
							break;
						case 'length':
							$orderMethod = 'length';
							break;
						case 'created':
							$orderMethod = 'created';
							break;
						case 'sortkey':
						case 'categorysortkey':
							$orderMethod = 'categorysortkey';
							break;
						case 'popularity':
							$orderMethod = 'categoryadd'; // no HitCounters since MW1.25
							break;
						case 'categoryadd':
						default:
							$orderMethod = 'categoryadd';
							break;
					}
					break;
				case 'redirects':
					switch ( $arg ) {
						case 'include':
							$redirects = 'include';
							break;
						case 'only':
							$redirects = 'only';
							break;
						case 'exclude':
						default:
							$redirects = 'exclude';
							break;
					}
					break;
				case 'stablepages':
					switch ( $arg ) {
						case 'include':
							$stable = 'include';
							break;
						case 'only':
							$flaggedRevs = true;
							$stable = 'only';
							break;
						case 'exclude':
						default:
							$flaggedRevs = true;
							$stable = 'exclude';
							break;
					}
					break;
				case 'qualitypages':
					switch ( $arg ) {
						case 'include':
							$quality = 'include';
							break;
						case 'only':
							$flaggedRevs = true;
							$quality = 'only';
							break;
						case 'exclude':
						default:
							$flaggedRevs = true;
							$quality = 'exclude';
							break;
					}
					break;
				case 'suppresserrors':
					if ( $arg == 'true' ) {
						$suppressErrors = true;
					} else {
						$suppressErrors = false;
					}
					break;
				case 'addfirstcategorydate':
					if ( $arg === 'true' ) {
						$addFirstCategoryDate = true;
					} elseif ( preg_match( '/^(?:[ymd]{2,3}|ISO 8601)$/', $arg ) ) {
						// if it more or less is valid dateformat.
						$addFirstCategoryDate = true;
						$dateFormat = $arg;
						if ( strlen( $dateFormat ) == 2 ) {
							$dateFormat = $dateFormat . 'y'; # DateFormatter does not support no year. work around
							$stripYear = true;
						}
					} else {
						$addFirstCategoryDate = false;
					}
					break;
				case 'shownamespace':
					$showNamespace = $arg !== 'false';
					break;
				case 'ignoresubpages':
					$ignoreSubpages = ( $arg === 'true' );
					break;
				case 'googlehack':
					$googleHack = $arg !== 'false';
					break;
				case 'nofollow': # bug 6658
					if ( $arg !== 'false' ) {
						$linkOptions['rel'] = 'nofollow';
					}
					break;
			} // end main switch()
		} // end foreach()

		$catCount = count( $categories );
		$excludeCatCount = count( $excludeCategories );
		$totalCatCount = $catCount + $excludeCatCount;

		if ( $catCount < 1 && !$namespaceFiltering ) {
			if ( $suppressErrors ) {
				return '';
			}

			// "!!no included categories!!"
			return wfMessage( 'intersection_noincludecats' )->inContentLanguage()->escaped();
		}

		if ( $totalCatCount > $wgDLPmaxCategories && !$wgDLPAllowUnlimitedCategories ) {
			if ( $suppressErrors ) {
				return '';
			}

			// "!!too many categories!!"
			return wfMessage( 'intersection_toomanycats' )->inContentLanguage()->escaped();
		}

		if ( $countSet ) {
			if ( $count < 1 ) {
				$count = 1;
			}
			if ( $count > $wgDLPMaxResultCount ) {
				$count = $wgDLPMaxResultCount;
			}
		} elseif ( !$wgDLPAllowUnlimitedResults ) {
			$count = $wgDLPMaxResultCount;
			$countSet = true;
		}

		// disallow showing date if the query doesn't have an inclusion category parameter
		if ( $catCount < 1 ) {
			$addFirstCategoryDate = false;
			// don't sort by fields relating to categories if there are no categories.
			if ( $orderMethod === 'categoryadd' || $orderMethod === 'categorysortkey' ) {
				$orderMethod = 'created';
			}
		}

		// build the SQL query
		$dbr = wfGetDB( DB_REPLICA );
		$tables = [ 'page' ];
		$fields = [ 'page_namespace', 'page_title' ];
		$where = [];
		$join = [];
		$options = [];

		if ( $googleHack ) {
			$fields[] = 'page_id';
		}

		if ( $addFirstCategoryDate ) {
			$fields[] = 'c1.cl_timestamp';
		}

		if ( $namespaceFiltering ) {
			$where['page_namespace'] = $namespaceIndex;
		}

		// Bug 14943 - Allow filtering based on FlaggedRevs stability.
		// Check if the extension actually exists before changing the query...
		if ( $flaggedRevs && ExtensionRegistry::getInstance()->isLoaded( 'FlaggedRevs' ) ) {
			$tables[] = 'flaggedpages';
			$join['flaggedpages'] = [ 'LEFT JOIN', 'page_id = fp_page_id' ];

			switch ( $stable ) {
				case 'only':
					$where[] = 'fp_stable IS NOT NULL';
					break;
				case 'exclude':
					$where['fp_stable'] = null;
					break;
			}

			switch ( $quality ) {
				case 'only':
					$where[] = 'fp_quality >= 1';
					break;
				case 'exclude':
					$where[] = 'fp_quality = 0 OR fp_quality IS NULL';
					break;
			}
		}

		switch ( $redirects ) {
			case 'only':
				$where['page_is_redirect'] = 1;
				break;
			case 'exclude':
				$where['page_is_redirect'] = 0;
				break;
		}

		if ( $ignoreSubpages ) {
			$where[] = "page_title NOT " .
				$dbr->buildLike( $dbr->anyString(), '/', $dbr->anyString() );
		}

		$currentTableNumber = 1;
		$categorylinks = 'categorylinks';

		if ( $useGallery && $pageImagesEnabled ) {
			$tables['pp1'] = 'page_props';
			$join['pp1'] = [
				'LEFT JOIN',
				[
					'pp1.pp_propname' => PageImages::PROP_NAME_FREE,
					'page_id = pp1.pp_page'
				]
			];
			$fields['pageimage_free'] = 'pp1.pp_value';

			$tables['pp2'] = 'page_props';
			$join['pp2'] = [
				'LEFT JOIN',
				[
					'pp2.pp_propname' => PageImages::PROP_NAME,
					'page_id = pp2.pp_page'
				]
			];
			$fields['pageimage_nonfree'] = 'pp2.pp_value';
		}

		foreach ( $categories as $cat ) {
			$join["c$currentTableNumber"] = [
				'INNER JOIN',
				[
					"page_id = c{$currentTableNumber}.cl_from",
					"c{$currentTableNumber}.cl_to={$dbr->addQuotes( $cat->getDBKey() )}"
				]
			];
			$tables["c$currentTableNumber"] = $categorylinks;

			$currentTableNumber++;
		}

		foreach ( $excludeCategories as $cat ) {
			$join["c$currentTableNumber"] = [
				'LEFT OUTER JOIN',
				[
					"page_id = c{$currentTableNumber}.cl_from",
					"c{$currentTableNumber}.cl_to={$dbr->addQuotes( $cat->getDBKey() )}"
				]
			];
			$tables["c$currentTableNumber"] = $categorylinks;
			$where["c{$currentTableNumber}.cl_to"] = null;
			$currentTableNumber++;
		}

		if ( $order === 'descending' ) {
			$sqlOrder = 'DESC';
		} else {
			$sqlOrder = 'ASC';
		}

		switch ( $orderMethod ) {
			case 'lastedit':
				$sqlSort = 'page_touched';
				break;
			case 'length':
				$sqlSort = 'page_len';
				break;
			case 'created':
				$sqlSort = 'page_id'; // Since they're never reused and increasing
				break;
			case 'categorysortkey':
				$sqlSort = "c1.cl_type $sqlOrder, c1.cl_sortkey";
				break;
			case 'popularity':
				$sqlSort = 'page_counter';
				break;
			case 'categoryadd':
				$sqlSort = 'c1.cl_timestamp';
				break;
			default:
				// Should never reach here
				throw new MWException( "Invalid ordermethod $orderMethod" );
		}

		$options['ORDER BY'] = "$sqlSort $sqlOrder";

		if ( $countSet ) {
			$options['LIMIT'] = $count;
		}
		if ( $offset > 0 ) {
			$options['OFFSET'] = $offset;
		}

		// process the query
		$res = $dbr->select( $tables, $fields, $where, __METHOD__, $options, $join );

		if ( $dbr->numRows( $res ) == 0 ) {
			if ( $suppressErrors ) {
				return '';
			}

			return wfMessage( 'intersection_noresults' )->inContentLanguage()->escaped();
		}

		// start unordered list
		$output = $startList . "\n";

		$categoryDate = '';
		$df = null;
		if ( $dateFormat !== '' && $addFirstCategoryDate ) {
			$df = DateFormatter::getInstance();
		}

		// process results of query, outputing equivalent of <li>[[Article]]</li>
		// for each result, or something similar if the list uses other
		// startlist/endlist
		$articleList = [];
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		foreach ( $res as $row ) {
			$title = Title::makeTitle( $row->page_namespace, $row->page_title );
			if ( $addFirstCategoryDate ) {
				if ( $dateFormat !== '' ) {
					// this is a tad ugly
					// use DateFormatter, and support disgarding year.
					$categoryDate = wfTimestamp( TS_ISO_8601, $row->cl_timestamp );
					if ( $stripYear ) {
						$categoryDate = $contLang->getMonthName( (int)substr( $categoryDate, 5, 2 ) )
							. ' ' . substr( $categoryDate, 8, 2 );
					} else {
						$categoryDate = substr( $categoryDate, 0, 10 );
					}
					$categoryDate = $df->reformat( $dateFormat, $categoryDate, [ 'match-whole' ] );
				} else {
					$categoryDate = $contLang->date( wfTimestamp( TS_MW, $row->cl_timestamp ) );
				}
				if ( $useGallery ) {
					$categoryDate .= ' ';
				} else {
					$categoryDate .= wfMessage( 'colon-separator' )->text();
				}
			}

			$query = [];

			if ( $googleHack ) {
				$query['dpl_id'] = intval( $row->page_id );
			}

			if ( $showNamespace ) {
				$titleText = $title->getPrefixedText();
			} else {
				$titleText = $title->getText();
			}

			if ( $useGallery ) {
				$file = null;
				$link = '';
				if ( $galleryFileName ) {
					$link = $linkRenderer->makeKnownLink(
						$title,
						$titleText,
						[ 'class' => 'galleryfilename galleryfilename-truncate' ]
					) . "\n";
				}

				if ( $title->getNamespace() !== NS_FILE && $pageImagesEnabled ) {
					$file = $row->pageimage_free ?: $row->pageimage_nonfree;
				}

				// Note, $categoryDate is treated as raw html
				// this is safe since the only html present
				// would come from the dateformatter <span>.
				if ( $file !== null ) {
					$gallery->add(
						Title::makeTitle( NS_FILE, $file ),
						$link . $categoryDate,
						$file,
						$title->getLinkURL()
					);
				} else {
					$gallery->add(
						$title,
						$link . $categoryDate,
						$title->getText()
					);
				}
			} else {
				$articleList[] = htmlspecialchars( $categoryDate ) .
					Linker::link(
						$title,
						htmlspecialchars( $titleText ),
						$linkOptions,
						$query,
						[ 'forcearticlepath', 'known' ]
					);
			}
		}

		// end unordered list
		if ( $useGallery ) {
			$gallery->setHideBadImages();
			$gallery->setShowFilename( false );
			$gallery->setShowBytes( $galleryFileSize );
			if ( $galleryImageHeight > 0 ) {
				$gallery->setHeights( (string)$galleryImageHeight );
			}
			if ( $galleryImageWidth > 0 ) {
				$gallery->setWidths( (string)$galleryImageWidth );
			}
			if ( $galleryNumbRows > 0 ) {
				$gallery->setPerRow( $galleryNumbRows );
			}
			if ( $galleryCaption !== '' ) {
				$gallery->setCaption( $galleryCaption ); // gallery class escapes string
			}
			$output = $gallery->toHtml();
		} else {
			$output .= $startItem;
			if ( $inlineMode ) {
				$output .= $contLang->commaList( $articleList );
			} else {
				$output .= implode( "$endItem \n$startItem", $articleList );
			}
			$output .= $endItem;
			$output .= $endList . "\n";
		}

		return $output;
	}
}
