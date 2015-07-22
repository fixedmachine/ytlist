<?php
/******************************************************************************
 * ytlist snippet class
 ****************************************************************************** 

Snippet Name: 		ytlist
Short Description: 	Snippet for listing YouTube videos
Version: 			0.1
Author: 			Radosław Włodkowski (radoslaw@wlodkowski.net)

Note:
-----
You can use this snippet in anyway you want. Modify it's code, lend some parts of it or whatever you want to do with it.
If you are going to make changes to some other files that this snippet uses, make sure you keep the author informations intact at the code.


Copyright & Licencing - MIT License:
------------------------------------

Copyright (c) 2010 Radosław Włodkowski

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.


/*============================================================================*
 * Class definition
 *============================================================================*/

 class YTList {
	
	var $version;
	var $name;
	var $config = array();
	var $params = array();
	var $playListXML;
	var $feedListXML;
	var $include_path = "includes/";
	
	function __construct() {
		global $modx;
	}
	
	function Run() {
		global $modx;
		
		// Retrieve config information
		$this->InitConfig();
		
		if ($config === FALSE) {
			return FALSE;
		}
		
		switch($this->config['mode']){
				
				case 'feedList': 
									$output = $this->ShowVideos(); 
									break;
									
				case 'playList':
									if(!isset($_GET['pid'])){
										$output = $this->ShowPlaylists(); 
										break;
									}
									
				default: 
									$output = $this->ShowVideos();
				
		}
		
		
		
		return $output;
	}
	
	function ShowPlaylists(){
		// generate feed URL
		$feedURL = "http://gdata.youtube.com/feeds/api/users/{$this->config['ytUserName']}/playlists";
      
		// read feed into SimpleXML object
		$sxml = simplexml_load_file($feedURL);
		
		// get summary counts from opensearch: namespace
		$counts = $sxml->children('http://a9.com/-/spec/opensearchrss/1.0/');
		$total = $counts->totalResults; 
		$startOffset = $counts->startIndex; 
		$endOffset = ($startOffset-1) + $counts->itemsPerPage;       
		
		$inner = '';
		
		foreach ($sxml->entry as $entry) {
			// get <yt:playlistId>
			$yt = $entry->children('http://gdata.youtube.com/schemas/2007');
			$playlistId = $yt->playlistId;
			$description = $yt->description;
			
			$title = $entry->title;
			
			$entryPlaceholders = array(
				
				'ytl.title' => $title,
				'ytl.description' => $description,
				'ytl.playlistId' => $playlistId
				
			);
			
			$inner .= $this->replace($entryPlaceholders, $this->config['tplPlaylistEntry']);
			
		}
		
		$wrapperPlaceholders = array(
				
			'ytl.playlistWrapper' => $inner
			
		);

		$output .= $this->replace($wrapperPlaceholders, $this->config['tplPlaylistWrapper']);
		
		return $output;
	}
	
	function ShowVideos(){
		
		if(!isset($_GET['pid']) && !isset($this->config['playlistID'])){
			return 'no pid set';
		}
		
		if(isset($_GET['pid'])){
			$this->config['playlistID'] = $_GET['pid'];
		}
		
		// set max results per page
		$i = $this->config['resultsOnPage'];
		
		 if (!isset($_GET['pageID']) || $_GET['pageID'] <= 0) {
			$o = 1;
		} else {     
			$pageID = htmlentities($_GET['pageID']);
			$o = (($pageID-1) * $i)+1;  
		}
		
		// generate feed URL
		$feedURL = "http://gdata.youtube.com/feeds/api/playlists/{$this->config['playlistID']}?max-results={$i}&start-index={$o}";
      
		// read feed into SimpleXML object
		$sxml = simplexml_load_file($feedURL);
      
		  // get summary counts from opensearch: namespace
		  $counts = $sxml->children('http://a9.com/-/spec/opensearchrss/1.0/');
		  $total = $counts->totalResults; 
		  $startOffset = $counts->startIndex; 
		  $endOffset = ($startOffset-1) + $counts->itemsPerPage;       
      
	  
      // include Pager class
      require_once 'Pager/Pager.php';
      $params = array(
          'mode'       => 'Jumping',
          'perPage'    => $i,
          'delta'      => 5,
          'totalItems' => $total,
		  'append'		=> false,
		  'path'		=> $modx->config['base_url'],
		  'fileName'	=> '[~[*id*]~]?pageID=%d'.(isset($this->config['playlistID'])?'&pid='.$this->config['playlistID']:''),
		  'prevImg'		=> '&laquo;Poprzednie',
		  'nextImg'		=> 'Następne&raquo;'
      );
	  
      $pager = & Pager::factory($params);
      $links = $pager->getLinks();     
		
		
		$output = '';
		$inner = '';
		
			foreach ($sxml->entry as $entry) {
				// get nodes in media: namespace for media information
				$media = $entry->children('http://search.yahoo.com/mrss/');
				
				if ($media->group->thumbnail && $media->group->thumbnail[0]->attributes()) {
					// get video player URL
					$attrs = $media->group->player->attributes();
					$watch = $attrs['url']; 
					
					
					$media->group->registerXPathNamespace('yt', 'http://gdata.youtube.com/schemas/2007');
					$media->group->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');
					//$attrs = $media->group->content[0]->attributes();
					//$mediaContentUrl = $attrs['url']; 			
					$content = $media->group->xpath('media:content[@yt:format="5"]');
					//$content->registerXPathNamespace('yt', 'http://gdata.youtube.com/schemas/2007');
					$mediaContentUrl = $content[0]->attributes()->url;
					
					
					//$attrs = $media->group->content->attributes();
					//$mediaContentUrl = $attrs['url']; 
					
					// get video thumbnails
					
					$thumbnail = $media->group->xpath('media:thumbnail[@width>"120"]');
					$thumbnailHQ = $thumbnail[0]->attributes()->url;
					
					$attrs = $media->group->thumbnail[0]->attributes();
					$thumbnail = $attrs['url']; 
					
					// get <yt:duration> node for video length
					$yt = $media->children('http://gdata.youtube.com/schemas/2007');
					$attrs = $yt->duration->attributes();
					$length = $attrs['seconds']; 
					
					// get <yt:stats> node for viewer statistics
					$yt = $entry->children('http://gdata.youtube.com/schemas/2007');
					
					if ($yt->statistics && $yt->statistics->attributes()) {
						$attrs = $yt->statistics->attributes();
						$viewCount = (int) $attrs['viewCount'];
					}
					
					// get <gd:rating> node for video ratings
					$gd = $entry->children('http://schemas.google.com/g/2005'); 
					if ($gd->rating) {
					  $attrs = $gd->rating->attributes();
					  $rating = $attrs['average']; 
					} else {
					  $rating = 0; 
					}

					// get video ID
					$arr = explode('/',$entry->id);
					$id = $arr[count($arr)-1];
					
					$title = $media->group->title;
					$description = $media->group->description;
					
					$entryPlaceholders = array(
						
						'ytl.title' => $title,
						'ytl.description' => $description,
						'ytl.playlistId' => $playlistId,
						'ytl.watchLink'	=> $watch,
						'ytl.mediaContentUrl'	=> $mediaContentUrl,
						'ytl.img0' => $thumbnail,
						'ytl.imgHQ'	=> $thumbnailHQ
					);
					
					$inner .= $this->replace($entryPlaceholders, $this->config['tplVideoEntry']);
				
				}
			}

			$wrapperPlaceholders = array(
				'ytl.previous'			=> $links['back'],
				'ytl.next'				=> $links['next'],
				'ytl.pages'				=> $links['pages'],
				'ytl.first'				=> $links['first'],
				'ytl.last'				=> $links['last'],
				'ytl.paginationLinks'	=> $links['all'],
				'ytl.linktags'			=> $links['linktags'],
				'ytl.linkTagsRaw'		=> $links['linkTagsRaw'],
				'ytl.videoWrapper' 		=> $inner
				
			);
			
			$output .= $this->replace($wrapperPlaceholders, $this->config['tplVideoWrapper']);
			
		return $output;
	}

	function GetParam($field) {
		return $this->params[$field];
	}

	function SetParam($field, $value) {
		$this->params[$field] = $value;
	}
	
	function InitConfig(){
		global $modx;
		
		$this->config['mode'] = !is_null($this->GetParam('mode')) ? $this->GetParam('mode'):'feedList';
		$this->config['ytUserName']  = !is_null($this->GetParam('ytUserName')) ? $this->GetParam('ytUserName'):'';
		$this->config['playlistID'] = !is_null($this->GetParam('playlistID')) ? $this->GetParam('playlistID'):'';
		$this->config['resultsOnPage'] = !is_null($this->GetParam('resultsOnPage')) ? intval($this->GetParam('resultsOnPage')):10;
		
		//templates
		$this->config['tplPlaylistWrapper'] = !is_null($this->GetParam('tplPlaylistWrapper')) ? $modx->getChunk($this->GetParam('tplPlaylistWrapper')) :'<div></div>';
		$this->config['tplPlaylistEntry'] = !is_null($this->GetParam('tplPlaylistEntry')) ? $modx->getChunk($this->GetParam('tplPlaylistEntry')) :'<div><h3><a href="[~[*id*]~]?pid=[+ytl.playlistId+]">[+ytl.title+]</a></h3><p>[+ytl.description+]</p></div>';
		$this->config['tplVideoWrapper'] = !is_null($this->GetParam('tplVideoWrapper')) ? $modx->getChunk($this->GetParam('tplVideoWrapper')) :'<div></div>';
		$this->config['tplVideoEntry'] = !is_null($this->GetParam('tplVideoEntry')) ? $modx->getChunk($this->GetParam('tplVideoEntry')) :'<div><h3><a href="[+ytl.watchLink+]">[+ytl.title+]</a></h3><p>[+ytl.description+]</p></div>';
	}
	
	function replace( $placeholders, $tpl ) {
		$keys = array();
		$values = array();
		foreach ($placeholders as $key=>$value) {
			$keys[] = '[+'.$key.'+]';
			$values[] = $value;
		}
		return str_replace($keys,$values,$tpl);
	}
} // end class
?>
