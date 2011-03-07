<?php
/*

 Purpose:       outputs a bulleted list of most recent
                items residing in a category, or a union
                of several categories.

 Contributors: n:en:User:IlyaHaykinson n:en:User:Amgine
 http://en.wikinews.org/wiki/User:Amgine
 http://en.wikinews.org/wiki/User:IlyaHaykinson

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 http://www.gnu.org/copyleft/gpl.html

 Current feature request list
	 1. Unset cached of calling page
	 2. RSS feed output? (GNSM extension?)

 To install, add following to LocalSettings.php
   include("$IP/extensions/intersection/DynamicPageList.php");

*/

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is not a valid entry point to MediaWiki.' );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'DynamicPageList',
	'version'        => '1.5',
	'descriptionmsg' => 'intersection-desc',
	'url'            => 'http://www.mediawiki.org/wiki/Extension:Intersection',
	'author'         => array( '[http://en.wikinews.org/wiki/User:Amgine Amgine]', '[http://en.wikinews.org/wiki/User:IlyaHaykinson IlyaHaykinson]' ),
);

// Internationalization file
$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['DynamicPageList'] = $dir . 'DynamicPageList.i18n.php';

$wgParserTestFiles[] = $dir . 'DynamicPageList.tests.txt';

# Configuration variables
$wgDLPmaxCategories = 6;                // Maximum number of categories to look for
$wgDLPMaxResultCount = 200;             // Maximum number of results to allow
$wgDLPAllowUnlimitedResults = false;    // Allow unlimited results
$wgDLPAllowUnlimitedCategories = false; // Allow unlimited categories

$wgHooks['ParserFirstCallInit'][] = 'wfDynamicPageList';
/**
 * Set up the <DynamicPageList> tag.
 *
 * @param $parser Object: instance of Parser
 * @return Boolean: true
 */
function wfDynamicPageList( &$parser ) {
	$parser->setHook( 'DynamicPageList', 'renderDynamicPageList' );
	return true;
}

// The callback function for converting the input text to HTML output
function renderDynamicPageList( $input ) {
	global $wgUser, $wgLang, $wgContLang;
	global $wgDisableCounters; // to determine if to allow sorting by #hits.
	global $wgDLPmaxCategories, $wgDLPMaxResultCount;
	global $wgDLPAllowUnlimitedResults, $wgDLPAllowUnlimitedCategories;

	$bCountSet = false;

	$sStartList = '<ul>';
	$sEndList = '</ul>';
	$sStartItem = '<li>';
	$sEndItem = '</li>';
	$bInlineMode = false;

	$bUseGallery = false;
	$bGalleryFileSize = false;
	$bGalleryFileName = true;
	$iGalleryImageHeight = 0;
	$iGalleryImageWidth = 0;
	$iGalleryNumbRows = 0;
	$sGalleryCaption = '';
	$gallery = null;

	$sOrderMethod = 'categoryadd';
	$sOrder = 'descending';
	$sRedirects = 'exclude';
	$sStable = $sQuality = 'include';
	$bFlaggedRevs = false;

	$bNamespace = false;
	$iNamespace = 0;

	$iOffset = 0;

	$bGoogleHack = false;

	$bSuppressErrors = false;
	$bShowNamespace = true;
	$bAddFirstCategoryDate = false;
	$sDateFormat = '';
	$bStripYear = false;

	$aLinkOptions = array();
	$aCategories = array();
	$aExcludeCategories = array();

	$aParams = explode( "\n", $input );

	$parser = new Parser;
	$poptions = new ParserOptions;

	foreach ( $aParams as $sParam )	{
		$aParam = explode( '=', $sParam, 2 );
		if( count( $aParam ) < 2 ) {
			continue;
		}
		$sType = trim( $aParam[0] );
		$sArg = trim( $aParam[1] );
		switch ( $sType ) {
			case 'category':
				$title = Title::newFromText(
					$parser->transformMsg( $sArg, $poptions )
				);
				if( is_null( $title ) ) {
					continue;
				}
				$aCategories[] = $title;
				break;
			case 'notcategory':
				$title = Title::newFromText(
					$parser->transformMsg( $sArg, $poptions )
				);
				if( is_null( $title ) ) {
					continue;
				}
				$aExcludeCategories[] = $title;
				break;
			case 'namespace':
				$ns = $wgContLang->getNsIndex( $sArg );
				if ( $ns != null ) {
					$iNamespace = $ns;
					$bNamespace = true;
				} else {
					$iNamespace = intval( $sArg );
					if ( $iNamespace >= 0 )	{
						$bNamespace = true;
					} else {
						$bNamespace = false;
					}
				}
				break;
			case 'count':
				// ensure that $iCount is a number;
				$iCount = intval( $sArg );
				$bCountSet = true;
				break;
			case 'offset':
				$iOffset = intval( $sArg );
				break;
			case 'imagewidth':
				$iGalleryImageWidth = intval( $sArg );
				break;
			case 'imageheight':
				$iGalleryImageHeight = intval( $sArg );
				break;
			case 'imagesperrow':
				$iGalleryNumbRows = intval( $sArg );
				break;
			case 'mode':
				switch ( $sArg ) {
					case 'gallery':
						$bUseGallery = true;
						$gallery = new ImageGallery;
						$sStartList = '';
						$sEndList = '';
						$sStartItem = '';
						$sEndItem = '';
						break;
					case 'none':
						$sStartList = '';
						$sEndList = '';
						$sStartItem = '';
						$sEndItem = '<br />';
						$bInlineMode = false;
						break;
					case 'ordered':
						$sStartList = '<ol>';
						$sEndList = '</ol>';
						$sStartItem = '<li>';
						$sEndItem = '</li>';
						$bInlineMode = false;
						break;
					case 'inline':
						// aka comma seperated list
						$sStartList = '';
						$sEndList = '';
						$sStartItem = '';
						$bInlineMode = true;
						break;
					case 'unordered':
					default:
						$sStartList = '<ul>';
						$sEndList = '</ul>';
						$sStartItem = '<li>';
						$sEndItem = '</li>';
						$bInlineMode = false;
						break;
				}
				break;
			case 'gallerycaption':
				// Should perhaps actually parse caption instead
				// as links and what not in caption might be useful.
				$sGalleryCaption = $parser->transformMsg( $sArg, $poptions );
				break;
			case 'galleryshowfilesize':
				switch ( $sArg ) {
					case 'no':
					case 'false':
						$bGalleryFileSize = false;
						break;
					case 'true':
					default:
						$bGalleryFileSize = true;
				}
				break;
			case 'galleryshowfilename':
				switch ( $sArg ) {
					case 'no':
					case 'false':
						$bGalleryFileName = false;
						break;
					case 'true':
					default:
						$bGalleryFileName = true;
						break;
				}
				break;
			case 'order':
				switch ( $sArg ) {
					case 'ascending':
						$sOrder = 'ascending';
						break;
					case 'descending':
					default:
						$sOrder = 'descending';
						break;
				}
				break;
			case 'ordermethod':
				switch ( $sArg ) {
					case 'lastedit':
						$sOrderMethod = 'lastedit';
						break;
					case 'length':
						$sOrderMethod = 'length';
						break;
					case 'created':
						$sOrderMethod = 'created';
						break;
					case 'sortkey':
					case 'categorysortkey':
						$sOrderMethod = 'categorysortkey';
						break;
					case 'popularity':
						if ( !$wgDisableCounters ) {
							$sOrderMethod = 'popularity';
						} else {
							$sOrderMethod = 'categoyadd'; // default if hitcounter disabled.
						}
						break;
					case 'categoryadd':
					default:
						$sOrderMethod = 'categoryadd';
						break;
				}
				break;
			case 'redirects':
				switch ( $sArg ) {
					case 'include':
						$sRedirects = 'include';
						break;
					case 'only':
						$sRedirects = 'only';
						break;
					case 'exclude':
					default:
						$sRedirects = 'exclude';
						break;
				}
				break;
			case 'stablepages':
				switch ( $sArg ) {
					case 'include':
						$sStable = 'include';
						break;
					case 'only':
						$bFlaggedRevs = true;
						$sStable = 'only';
						break;
					case 'exclude':
					default:
						$bFlaggedRevs = true;
						$sStable = 'exclude';
						break;
				}
				break;
			case 'qualitypages':
				switch ( $sArg ) {
					case 'include':
						$sQuality = 'include';
						break;
					case 'only':
						$bFlaggedRevs = true;
						$sQuality = 'only';
						break;
					case 'exclude':
					default:
						$bFlaggedRevs = true;
						$sQuality = 'exclude';
						break;
				}
				break;
			case 'suppresserrors':
				if ( $sArg == 'true' ) {
					$bSuppressErrors = true;
				} else {
					$bSuppressErrors = false;
				}
				break;
			case 'addfirstcategorydate':
				if ( $sArg == 'true' ) {
					$bAddFirstCategoryDate = true;
				} elseif ( preg_match( '/^(?:[ymd]{2,3}|ISO 8601)$/', $sArg ) )  {
					// if it more or less is valid dateformat.
					$bAddFirstCategoryDate = true;
					$sDateFormat = $sArg;
					if ( strlen( $sDateFormat ) == 2 ) {
						$sDateFormat = $sDateFormat . 'y'; # DateFormatter does not support no year. work arround
						$bStripYear = true;
					}
				} else {
					$bAddFirstCategoryDate = false;
				}
				break;
			case 'shownamespace':
				if ( 'false' == $sArg ) {
					$bShowNamespace = false;
				} else {
					$bShowNamespace = true;
				}
				break;
			case 'googlehack':
				if ( 'false' == $sArg ) {
					$bGoogleHack = false;
				} else {
					$bGoogleHack = true;
				}
				break;
			case 'nofollow': # bug 6658
				if ( 'false' != $sArg ) {
					$aLinkOptions['rel'] = 'nofollow';
				}
				break;
		} // end main switch()
	} // end foreach()

	$iCatCount = count( $aCategories );
	$iExcludeCatCount = count( $aExcludeCategories );
	$iTotalCatCount = $iCatCount + $iExcludeCatCount;

	if ( $iCatCount < 1 && false == $bNamespace ) {
		if ( $bSuppressErrors == false ) {
			return htmlspecialchars( wfMsg( 'intersection_noincludecats' ) ); // "!!no included categories!!";
		} else {
			return '';
		}
	}

	if ( $iTotalCatCount > $wgDLPmaxCategories && !$wgDLPAllowUnlimitedCategories ) {
		if ( $bSuppressErrors == false ) {
			return htmlspecialchars( wfMsg( 'intersection_toomanycats' ) ); // "!!too many categories!!";
		} else {
			return '';
		}
	}

	if ( $bCountSet ) {
		if ( $iCount < 1 ) {
			$iCount = 1;
		}
		if ( $iCount > $wgDLPMaxResultCount ) {
			$iCount = $wgDLPMaxResultCount;
		}
	} elseif ( !$wgDLPAllowUnlimitedResults ) {
		$iCount = $wgDLPMaxResultCount;
		$bCountSet = true;
	}

	// disallow showing date if the query doesn't have an inclusion category parameter
	if ( $iCatCount < 1 ) {
		$bAddFirstCategoryDate = false;
		// don't sort by fields relating to categories if there are no categories.
		if ( $sOrderMethod == 'categoryadd' || $sOrderMethod == 'categorysortkey' ) {
			$sOrderMethod = 'created';
		}
	}

	// build the SQL query
	$dbr = wfGetDB( DB_SLAVE );
	$tables = array( 'page' );
	$fields = array( 'page_namespace', 'page_title' );
	$where = array();
	$join = array();
	$options = array();

	if ( $bGoogleHack ) {
		$fields[] = 'page_id';
	}

	if ( $bAddFirstCategoryDate ) {
		$fields[] = 'c1.cl_timestamp';
	}

	if ( $bNamespace == true ) {
		$where['page_namespace'] = $iNamespace;
	}

	// Bug 14943 - Allow filtering based on FlaggedRevs stability.
	// Check if the extension actually exists before changing the query...
	if ( function_exists( 'efLoadFlaggedRevs' ) && $bFlaggedRevs ) {
		$tables[] = 'flaggedpages';
		$join['flaggedpages'] = array( 'LEFT JOIN', 'page_id = fp_page_id' );

		switch( $sStable ) {
			case 'only':
				$where[] = 'fp_stable IS NOT NULL';
				break;
			case 'exclude':
				$where['fp_stable'] = null;
				break;
		}

		switch( $sQuality ) {
			case 'only':
				$where[] = 'fp_quality >= 1';
				break;
			case 'exclude':
				$where[] = 'fp_quality = 0 OR fp_quality IS NULL';
				break;
		}
	}

	switch ( $sRedirects ) {
		case 'only':
			$where['page_is_redirect'] = 1;
			break;
		case 'exclude':
			$where['page_is_redirect'] = 0;
			break;
	}

	$iCurrentTableNumber = 1;
	$categorylinks = $dbr->tableName( 'categorylinks' );

	for ( $i = 0; $i < $iCatCount; $i++ ) {
		$join["$categorylinks AS c$iCurrentTableNumber"] = array(
			'INNER JOIN',
			array(
				"page_id = c{$iCurrentTableNumber}.cl_from",
			 	"c{$iCurrentTableNumber}.cl_to={$dbr->addQuotes($aCategories[$i]->getDBKey())}"
			)
		);
		$tables[] = "$categorylinks AS c$iCurrentTableNumber";

		$iCurrentTableNumber++;
	}

	for ( $i = 0; $i < $iExcludeCatCount; $i++ ) {
		$join["$categorylinks AS c$iCurrentTableNumber"] = array(
			'LEFT OUTER JOIN',
			array(
				"page_id = c{$iCurrentTableNumber}.cl_from",
				"c{$iCurrentTableNumber}.cl_to={$dbr->addQuotes($aExcludeCategories[$i]->getDBKey())}"
			)
		);
		$tables[] = "$categorylinks AS c$iCurrentTableNumber";
		$where["c{$iCurrentTableNumber}.cl_to"] = null;
		$iCurrentTableNumber++;
	}

	if ( 'descending' == $sOrder ) {
		$sSqlOrder = 'DESC';
	} else {
		$sSqlOrder = 'ASC';
	}

	switch ( $sOrderMethod ) {
		case 'lastedit':
			$sSqlSort = 'page_touched';
			break;
		case 'length':
			$sSqlSort = 'page_len';
			break;
		case 'created':
			$sSqlSort = 'page_id'; # Since they're never reused and increasing
			break;
		case 'categorysortkey':
			$sSqlSort = "c1.cl_type $sSqlOrder, c1.cl_sortkey";
			break;
		case 'popularity':
			$sSqlSort = 'page_counter';
			break;
		case 'categoryadd':
		default:
			$sSqlSort = 'c1.cl_timestamp';
			break;
	}

	$options['ORDER BY'] = "$sSqlSort $sSqlOrder";

	if ( $bCountSet ) {
		$options['LIMIT'] = $iCount;
	}
	if ( $iOffset > 0 ) {
		$options['OFFSET'] = $iOffset;
	}

	// process the query
	$res = $dbr->select( $tables, $fields, $where, __METHOD__, $options, $join );
	$sk = $wgUser->getSkin();

	if ( $dbr->numRows( $res ) == 0 ) {
		if ( $bSuppressErrors == false ) {
			return htmlspecialchars( wfMsg( 'intersection_noresults' ) );
		} else {
			return '';
		}
	}

	// start unordered list
	$output = $sStartList . "\n";

	$categoryDate = '';
	$df = null;
	if ( $sDateFormat != '' && $bAddFirstCategoryDate ) {
		$df = DateFormatter::getInstance();
	}

	// process results of query, outputing equivalent of <li>[[Article]]</li>
	// for each result, or something similar if the list uses other
	// startlist/endlist
	$articleList = array();
	foreach ( $res as $row ) {
		$title = Title::makeTitle( $row->page_namespace, $row->page_title );
		if ( true == $bAddFirstCategoryDate ) {
			if ( $sDateFormat != '' ) {
				# this is a tad ugly
				# use DateFormatter, and support disgarding year.
				$categoryDate = wfTimestamp( TS_ISO_8601, $row->cl_timestamp );
				if ( $bStripYear ) {
					$categoryDate = $wgContLang->getMonthName( substr( $categoryDate, 5, 2 ) )
						. ' ' . substr ( $categoryDate, 8, 2 );
				} else {
					$categoryDate = substr( $categoryDate, 0, 10 );
				}
				$categoryDate = $df->reformat( $sDateFormat, $categoryDate, array( 'match-whole' ) );
			} else {
				$categoryDate = $wgLang->date( wfTimestamp( TS_MW, $row->cl_timestamp ) );
			}
			if ( !$bUseGallery ) {
				$categoryDate .= wfMsg( 'colon-separator' );
			} else {
				$categoryDate .= ' ';
			}
		}

		$query = array();

		if ( $bGoogleHack == true ) {
			$query['dpl_id'] = intval( $row->page_id );
		}

		if ( $bShowNamespace == true ) {
			$titleText = $title->getPrefixedText();
		} else {
			$titleText = $title->getText();
		}

		if ( $bUseGallery ) {
			# Note, $categoryDate is treated as raw html
			# this is safe since the only html present
			# would come from the dateformatter <span>.
			$gallery->add( $title, $categoryDate );
		} else {
			$articleList[] = $categoryDate .
				$sk->link(
					$title,
					htmlspecialchars( $titleText ),
					$aLinkOptions,
					$query,
					array( 'forcearticlepath', 'known' )
				);
		}
	}

	// end unordered list
	if ( $bUseGallery ) {
		$gallery->setHideBadImages();
		$gallery->setShowFilename( $bGalleryFileName );
		$gallery->setShowBytes( $bGalleryFileSize );
		if ( $iGalleryImageHeight > 0 ) {
			$gallery->setHeights( $iGalleryImageHeight );
		}
		if ( $iGalleryImageWidth > 0 ) {
			$gallery->setWidths( $iGalleryImageWidth );
		}
		if ( $iGalleryNumbRows > 0 ) {
			$gallery->setPerRow( $iGalleryNumbRows );
		}
		if ( $sGalleryCaption != '' ) {
			$gallery->setCaption( $sGalleryCaption ); # gallery class escapes string
		}
		$output = $gallery->toHtml();
	} else {
		$output .= $sStartItem;
		if ( $bInlineMode ) {
			$output .= $wgContLang->commaList( $articleList );
		} else {
			$output .= implode( "$sEndItem \n $sStartItem", $articleList );
		}
		$output .= $sEndItem;
		$output .= $sEndList . "\n";
	}
	return $output;
}
