<?php

/*

 Purpose:       outputs a bulleted list of most recent
                items residing in a category, or a union
                of several categories.

 Contributors: n:en:User:IlyaHaykinson n:en:User:Amgine
 https://en.wikinews.org/wiki/User:Amgine
 https://en.wikinews.org/wiki/User:IlyaHaykinson

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
 https://www.gnu.org/copyleft/gpl.html

 Current feature request list
	 1. Unset cached of calling page
	 2. RSS feed output? (GNSM extension?)

 To install, add following to LocalSettings.php
   include("$IP/extensions/intersection/DynamicPageList.php");

*/

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'intersection' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['DynamicPageList'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for DynamicPageList extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the DynamicPageList extension requires MediaWiki 1.25+' );
}
