<?php
/*
URI:http://www.webimpressions.co.uk/wp/plugins/eas/
URI:http://www.webimpressions.co.uk
 Description: ebay auction display widget. Vertical scrolling Ebay auction listing filtered by region, category and seller id.
 Author: Dave Heath
 Version: 1.0
 */

function eas_public_scripts(){
	wp_enqueue_script('jquery');
	wp_register_script('wi-scroller-js', WI_EAS_F_URL . 'js/wi-js.js');
	wp_enqueue_script(array('jquery', 'wi-scroller-js'));
}

function eas_public_styles(){
	wp_register_style('wi-scroller-css', WI_EAS_F_URL . 'css/wi-css.css');
	wp_enqueue_style('wi-scroller-css');
}

function getPrettyTimeFromEbayTime($eBayTimeString){
	// Input is of form 'PT12M25S'
	$matchAry = array(); // initialize array which will be filled in preg_match
	$pattern = "#P([0-9]{0,3}D)?T([0-9]?[0-9]H)?([0-9]?[0-9]M)?([0-9]?[0-9]S)#msiU";
	preg_match($pattern, $eBayTimeString, $matchAry);

	$days  = (int) $matchAry[1];
	$hours = (int) $matchAry[2];
	$min   = (int) $matchAry[3];    // $matchAry[3] is of form 55M - cast to int
	$sec   = (int) $matchAry[4];

	$retnStr = '';
	if ($days)  { $retnStr .= "$days day"    . pluralS($days);  }
	if ($hours) { $retnStr .= " $hours hour" . pluralS($hours); }
	if ($min)   { $retnStr .= " $min minute" . pluralS($min);   }
	if ($sec)   { $retnStr .= " $sec second" . pluralS($sec);   }

	return $retnStr;
}

function pluralS($intIn) {
	// if $intIn > 1 return an 's', else return null string
	if ($intIn > 1) {
		return 's';
	} else {
		return '';
	}
}

function getFindItemsAdvancedResultsAsXML($instance) {
	
	$debug=false;
	$ebay_global_id = stripslashes($instance['ebay_global_id']);
	$ebay_app_id = stripslashes($instance['ebay_app_id']);
	$s_endpoint = 'http://open.api.ebay.com/shopping';  // Shopping
	$f_endpoint = 'http://svcs.ebay.com/services/search/FindingService/v1';  // Finding
	$responseEncoding = 'XML';   // Format of the response
	$s_version = '667';   // Shopping API version number
	$f_version = '1.4.0';   // Finding API version number
	$results='';
	$maxEntries = 200;
	$itemType ='All';
	$itemsort="EndTimeSoonest";
	
	$results = '';   // local to this function
	// Construct the FindItems call
	$apicall = "$f_endpoint?OPERATION-NAME=findItemsAdvanced"
	. "&version=$f_version"
	. "&GLOBAL-ID=".$ebay_global_id
	. "&SECURITY-APPNAME=".$ebay_app_id   // replace this with your AppID
	. "&RESPONSE-DATA-FORMAT=".$responseEncoding
	. "&itemFilter(0).name=Seller"
	. "&itemFilter(0).value=".$instance['seller_id']
	. "&itemFilter(1).name=ListingType"
	. "&itemFilter(1).value=".$itemType
	. "&categoryId=".$instance['select_category']
	. "&paginationInput.entriesPerPage=".$instance['count']
	. "&sortOrder=".$itemsort;
	
	if($debug) {print "<br />findItemsAdvanced call = <blockquote>$apicall </blockquote>";exit; }	
	
	// Load the call and capture the document returned by the Finding API
	$session  = curl_init();
	if($session===false) {
		$resp = simplexml_load_file($apicall);
	} else {
		
		curl_setopt($session, CURLOPT_URL, $apicall);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		$responsexml = curl_exec($session);
		curl_close($session);
		$resp = simplexml_load_string($responsexml);
	}
	
	// Check to see if the response was loaded, else print an error
	if ($resp->ack == "Success") {
		$results= $resp;
	} else {
		$results = '';
	}
	
	return $results;	
}
	
function eas_scroller($instance) {

	$select_category = stripslashes($instance['select_category']);
	$count = intval($instance['count']);
	$ebay_global_id = stripslashes($instance['ebay_global_id']);
	$seller_id = stripslashes($instance['seller_id']);
	$show_seller = intval($instance['show_seller']);	
	$show_thumb = intval($instance['show_thumb']);
	$open_newtab = intval($instance['open_newtab']);
	$ebay_app_id = stripslashes($instance['ebay_app_id']);
	$strip_title = intval($instance['strip_title']);
	$strip_cat = intval($instance['strip_cat']);
	$category_name = stripslashes($instance['category_name']);
	$color_style = stripslashes($instance['color_style']);
	$enable_ticker = intval($instance['enable_ticker']);
	$visible_items = intval($instance['visible_items']);
	$ticker_speed = intval($instance['ticker_speed']) * 1000;
	$show_category=intval($instance['show_category']);

	
	if(empty($select_category) || $seller_id==''){
			return '';
	}

	$rand = array();
$i=0;
// Generate the Tabs
if($show_category){ // replace with option to show category above scroller
	echo '<ul class="wi-tab-wrap wi-tab-style-' . $color_style . ' wi-clearfix">';

		$rand[$i] = rand(0, 999);		
				if( (isset( $select_category) && !empty($select_category) && $select_category!=-1) ){

				if( $strip_cat >0 ){
					$titleLen = strlen($category_name);				
					$title = wp_html_excerpt( $category_name, $strip_cat );
					$title = ($titleLen > $strip_cat) ? $title . ' ...' : $title;
					
				} else {
					$title=$category_name;
				}
			echo '<li data-tab="wi-tab-' . $rand[$i] . '" data-value="'.$select_category.'">' . $title . '</li>';
			}			

	echo '</ul>';
}
		$item_feed= array();

		if($select_category!=-1){	
		
			$item_feed = getFindItemsAdvancedResultsAsXML($instance);
		} else {
			return '';
		}
		
		if(empty($item_feed)) return '';
		
		$maxitems = $item_feed->searchResult->item->count();
		$item_feed_title = esc_attr(strip_tags($item_feed->title));

		$randAttr = isset($rand[$i]) ? ' data-id="wi-tab-' . $rand[$i] . '" ' : '';

		// Outer Wrap start
		echo '<div class="wi-wrap ' . (($enable_ticker == 1 ) ? 'wi-vticker' : '' ) . ' wi-style-' . $color_style . '" data-visible="' . $visible_items . '" data-speed="' . $ticker_speed . '"' . $randAttr . '><div>';

		if ($maxitems == 0){
			echo '<div>No auctions.</div>';
		}else{
			$j=1;
			// Loop through each feed item
			foreach ($item_feed->searchResult->item as $item){
					
				$price = sprintf("%01.2f", $item->sellingStatus->convertedCurrentPrice);
				$ship  = sprintf("%01.2f", $item->shippingInfo->shippingServiceCost);
				$total = sprintf("%01.2f", ((float)$item->sellingStatus->convertedCurrentPrice
						+ (float)$item->shippingInfo->shippingServiceCost));
					
				// Determine currency to display - so far only seen cases where priceCurr = shipCurr, but may be others
				$priceCurr = (string) $item->sellingStatus->convertedCurrentPrice['currencyId'];
				$shipCurr  = (string) $item->shippingInfo->shippingServiceCost['currencyId'];
				if ($priceCurr == $shipCurr) {
					$curr = $priceCurr;
				} else {
					$curr = "$priceCurr / $shipCurr";  // potential case where price/ship currencies differ
				}
					
				$timeLeft = getPrettyTimeFromEbayTime($item->sellingStatus->timeLeft);
				//$endTime = strtotime($item->listingInfo->endTime);   // returns Epoch seconds
				$endTime = $item->listingInfo->endTime;

				// Get the link
				$link = $item->viewItemURL;
				while ( stristr($link, 'http') != $link ){ $link = substr($link, 1); }
				$link = esc_url(strip_tags($link));

				// Get the item title
				$title = esc_attr(strip_tags($item->title));
				if ( empty($title) )
					$title = __('No Title');

				if( $strip_title != 0 ){
					$titleLen = strlen($title);
					$title = wp_html_excerpt( $title, $strip_title );
					$title = ($titleLen > $strip_title) ? $title . ' ...' : $title;
				}

				// Open links in new tab
				$newtab = ($open_newtab) ? ' target="_blank"' : '';

				// Get thumbnail if present
				$thumb = '';
				if ($show_thumb == 1 ){
					$thumburl =  $item->galleryURL;
					if(empty($thumburl)) $thumburl="http://pics.ebaystatic.com/aw/pics/express/icons/iconPlaceholder_96x96.gif";

					$thumb = '<a href="'.$link.'" '.$newtab.'><img src="' . $thumburl . '" alt="' . $title . '" class="wi-thumb" align="left"/></a>';
				}

				$priceDetail='<div style="display:inline-block;"><span style="float:left;padding-left:14px;display:inline-block;">Cost:<br />Shipping<br />Total:<br />&nbsp;</span>';
				$priceDetail.='<span style="float:right;padding-left:7px;display:inline-block;">'.$price.'<br />'.$ship.'<br/>' . $total . '<br /> ' . $curr . '</span></div>';

				echo "\n\n\t";

				// Display the feed items
				echo '<div class="wi-item ' . (($j%2 == 0) ? 'even' : 'odd') . '">';
				echo '<div class="wi-title"><a href="' . $link . '"' . $newtab . ' title="Expires on ' . $endTime. '">' . $title . '</a></div>';
				echo $thumb.$priceDetail.'<div style="clear:both;"></div>';
				echo '<div class="wi-meta">';
				echo '<div style="font-size:80%;">'.$timeLeft.'<br /></div>';
				if($show_seller && !empty($seller_id))
					echo ' - <cite class="wi-author">' .$seller_id . '</cite>';

				echo '</div>';

				echo '</div>';
				// End display

				$j++;
			}
		}

		// Outer wrap end
		echo "\n\n</div>
		</div> \n\n" ;

		unset($item_feed);
	
}
class eas_scroller_f_widget extends WP_Widget{

	## Initialize
	function eas_scroller_f_widget(){
		$widget_ops = array(
				'classname' => 'widget_eas_f_scroller',
				'description' => "ebay auction scroller free widget"
		);

		$control_ops = array('width' => 430, 'height' => 500);
		parent::WP_Widget('eas_f_scroller', 'ebay auction scroller free', $widget_ops, $control_ops);
	}

	## Display the Widget
	function widget($args, $instance){

		extract($args);
		if(empty($instance['title'])){
			$title = '';
		}else{
			$title = $before_title . apply_filters('widget_title', $instance['title'], $instance, $this->id_base) . $after_title;
		}

		echo $before_widget . $title;
		echo "\n" . '
		<!-- Start ebay auction scroller v' . WI_EAS_F_VERSION . '-->
		<div class="wi-scroller-widget">' . "\n";

		eas_scroller($instance);

		echo "\n" . '</div>
		<!-- End - el scroller -->
		' . "\n";
		echo $after_widget;
	}

	## Save settings
	function update($new_instance, $old_instance){
		
		$instance = $old_instance;
		$instance['title'] = stripslashes($new_instance['title']);
		$instance['seller_id'] = isset($new_instance['seller_id'])?$new_instance['seller_id']:'';
		$instance['ebay_app_id'] = stripslashes($new_instance['ebay_app_id']);
		$instance['select_category']=stripslashes($new_instance['select_category']);
		$instance['ebay_global_id'] = stripslashes($new_instance['ebay_global_id']);
		$instance['category_name']=stripslashes($new_instance['category_name']);
		$instance['count'] = intval($new_instance['count']);
		$instance['show_seller'] = isset($new_instance['show_seller'])?1:0;
		$instance['show_thumb'] =  isset($new_instance['show_thumb'])?1:0;
		$instance['open_newtab'] = isset($new_instance['open_newtab'])?1:0;
		$instance['strip_title'] = intval($new_instance['strip_title']);
		$instance['strip_cat'] = intval($new_instance['strip_cat']);
		$instance['color_style'] = stripslashes($new_instance['color_style']);
		$instance['enable_ticker'] = isset($new_instance['enable_ticker'])?1:0;
		$instance['visible_items'] = intval($new_instance['visible_items']);
		$instance['ticker_speed'] = intval($new_instance['ticker_speed']);
		$instance['show_category']=isset($new_instance['show_category'])?1:0;
		
		return $instance;
	}

	##  Widget form
	function form($instance){
		global $eas_color_styles,$ebay_global_id_list;

		$instance = wp_parse_args( (array) $instance, array(
				'title' => '',
				'ebay_global_id'=>'EBAY-GB',
				'ebay_app_id' => '',
				'count' => 5,
				'seller_id' => '',
				'show_seller' => 0,
				'show_thumb' => 1,
				'open_newtab' => 1,
				'strip_title' => 0,
				'strip_cat' => 0,
				'select_category' => -1,
				'show_category' => 0,
				'category_name' => '',
				'color_style' => 'none',
				'enable_ticker' => 1, 'visible_items' => 5, 'ticker_speed' => 4		
		));
		
		$ebay_app_id = stripslashes($instance['ebay_app_id']);
		
		$title = stripslashes($instance['title']);
		$seller_id = stripslashes($instance['seller_id']);
		$ebay_global_id=stripslashes($instance['ebay_global_id']);
		$select_category= stripslashes($instance['select_category']);
		$show_seller = intval($instance['show_seller']);		
		$count = intval($instance['count']);
		$open_newtab = intval($instance['open_newtab']);	
		$strip_title = intval($instance['strip_title']);
		$strip_cat = intval($instance['strip_cat']);
		$show_thumb = intval($instance['show_thumb']);		
		$color_style = stripslashes($instance['color_style']);
		$enable_ticker = intval($instance['enable_ticker']);	
		$visible_items = intval($instance['visible_items']);
		$ticker_speed = intval($instance['ticker_speed']);
		$category_name = stripslashes($instance['category_name']);
		$show_category= intval($instance['show_category']);		
		
		$categories=getCats($ebay_app_id);

		?>
		<div class="eas_settings">
		<table>
		<tr>
		  <td style="width:30%;"><label for="<?php echo $this->get_field_id('ebay_app_id'); ?>"  title="Enter your Ebay developer app id">Ebay app ID</label></td>
		  <td style="width:70%;"><input id="<?php echo $this->get_field_id('ebay_app_id');?>" name="<?php echo $this->get_field_name('ebay_app_id'); ?>" type="text" value="<?php echo $ebay_app_id; ?>" class="widefat"  title="Enter your Ebay developer app id"/></td>
		</tr>
		</table>
		</div>		
		<?php	
		
		if(isset($ebay_app_id) && $ebay_app_id!='') {			
			$hideme='style="pointer-events:auto;"';			
			} else {
				$hideme='style="pointer-events:none;" ';
		?>
		<p>An Ebay app id is required to use this widget. You can learn more about this at <a href="https://go.developer.ebay.com/" target="blank_">go.developer.ebay.com</a></p>
		<p>Once you enter an app id the widget options will be available</p>
		<?php					
			}
		?>
		<div class="eas_settings" <?php echo $hideme; ?>>
		<h4>Settings</h4>
		<table>		
		<tr>
		  <td style="width:30%;"><label for="<?php echo $this->get_field_id('title'); ?>" title="To appear at the top of the widget, can be left blank">Title</label></td>
		  <td style="width:70%;"><input  id="<?php echo $this->get_field_id('title');?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" class="widefat"  title="To appear at the top of the widget, can be left blank" /></td>
		</tr>
		<tr>
		  <td><label for="<?php echo $this->get_field_id('seller_id'); ?>" title="Enter your Ebay seller id. Required!">Seller ID *</label></td>
		  <td><input id="<?php echo $this->get_field_id('seller_id'); ?>" name="<?php echo $this->get_field_name('seller_id'); ?>" type="text" value="<?php echo $seller_id; ?>" class="widefat"  title="Enter your Ebay seller id, Required!"  /></td>
		</tr>
		 <tr>
			<td><label for="<?php echo $this->get_field_id('ebay_global_id'); ?>" title="select the server locale for the search">Ebay server / region</label></td>
			<td>
			<?php
			echo '<select name="' . $this->get_field_name('ebay_global_id') . '" id="' . $this->get_field_id('ebay_global_id') . '" title="select the server locale for the search" >';
			foreach($ebay_global_id_list as $k => $v){
				echo '<option value="' . $k . '" ' . ($ebay_global_id == $k ? 'selected="selected"' : "") .  '>' . $v . '</option>';
			}
			echo '</select>';
			?>
			</td>
		  </tr>
		  <tr>
		 <td>
		 <label for="<?php echo $this->get_field_id('select_category'); ?>" title="select a category to display">category</label>
		</td>
		<td>		
		<?php 
			$catname=array('none');
			$catid=array(-1,);
			
			if($categories)				
				
				echo '<select  onChange="updateName(this);" name="' . $this->get_field_name('select_category').'" id="' . $this->get_field_id('select_category') . '" title="select a category to display" >
				<option value="-1" '.($select_category==-1?'selected="selected"' : "").' >none</option>';
			
					foreach($categories[0] as $k => $v) {
							echo '<option value="'.$v->CategoryID.'" ';
						if($v->CategoryID==$select_category) {							
							echo ' selected="selected"  >'.$v->CategoryName.'</option>';
							$catname=$v->CategoryName;
							$catid=$v->CategoryID;
						}else {
							echo ' >'.$v->CategoryName.'</option>';						
						}					
					}

				echo '</select><input type="hidden" name="'.$this->get_field_name('category_name').'" id="' . $this->get_field_id('category_name'). '" value="'.$catname.'" />';				
					?>
					</td>
					</tr>
					<tr>
					<td><label for="<?php echo $this->get_field_id('show_category'); ?>" title="show the category name above the scroller">show category</label></td>	
					<td><input id="<?php echo $this->get_field_id('show_category');?>" type="checkbox"  name="<?php echo $this->get_field_name('show_category'); ?>" value="1" <?php echo $show_category == "1" ? 'checked="checked"' : ""; ?> title="show the category name above the scroller" /></td>
					</tr>

			<script type="text/javascript">
			function updateName(select,indx) {
				name=select.options[select.selectedIndex].text;
				cat=select.options[select.selectedIndex].value;							
				jQuery('#<?php echo $this->get_field_id('category_name'); ?>').val(name);			
			}
			</script>
			</table>
			<br />
			<br />
			<table>			
	  	  	<tr>
		  	<td style="width:30%;"><label for="<?php echo $this->get_field_id('show_seller');?>" title="Show the seller name in the auction">Show Seller</label></td>
			<td><input id="<?php echo $this->get_field_id('show_seller');?>" type="checkbox"  name="<?php echo $this->get_field_name('show_seller'); ?>" value="1" <?php echo $show_seller == "1" ? 'checked="checked"' : ""; ?> title="Show the seller name in the auction" /></td>
			<td style="width:25%;"><label for="<?php echo $this->get_field_id('count');?>" title="No of auctions to retrieve">Count</label></td>
			<td style="width:20%;"><input id="<?php echo $this->get_field_id('count');?>" name="<?php echo $this->get_field_name('count'); ?>" type="text" value="<?php echo $count; ?>" class="widefat" title="No of auctions to retrieve"/></td>
		 	</tr>
		    <tr>
		  	<td><label for="<?php echo $this->get_field_id('open_newtab'); ?>" title="open auction in a new tab/window.">Open in new tab</label></td>
		    <td><input id="<?php echo $this->get_field_id('open_newtab'); ?>" type="checkbox"  name="<?php echo $this->get_field_name('open_newtab'); ?>" value="1" <?php echo $open_newtab == "1" ? 'checked="checked"' : ""; ?> title="open auction in a new tab/window." /></td>		    
		    <td><label for="<?php echo $this->get_field_id('strip_title'); ?>" title="The number of characters displayed for auction title. Use 0 to disable stripping">Strip Title</label></td>
		    <td><input id="<?php echo $this->get_field_id('strip_title');?>" name="<?php echo $this->get_field_name('strip_title'); ?>" type="text" value="<?php echo $strip_title; ?>" class="widefat" title="The number of characters displayed for auction title. Use 0 to disable stripping"/></td>
	      	</tr>
		  	<tr>
		  	<td><label for="<?php echo $this->get_field_id('show_thumb'); ?>"title="Show thumbnail if available.">Show thumbnail</label></td>
		    <td><input id="<?php echo $this->get_field_id('show_thumb'); ?>" type="checkbox"  name="<?php echo $this->get_field_name('show_thumb'); ?>" value="1" <?php echo $show_thumb == "1" ? 'checked="checked"' : ""; ?> title="Show thumbnail if available." /></td>
		    <td><label for="<?php echo $this->get_field_id('strip_cat'); ?>" title="The number of characters displayed for the category name. Use 0 to disable stripping">Strip category</label></td>
		    <td><input id="<?php echo $this->get_field_id('strip_cat');?>" name="<?php echo $this->get_field_name('strip_cat'); ?>" type="text" value="<?php echo $strip_cat; ?>" class="widefat" title="The number of characters displayed for the category name. Use 0 to disable stripping"/></td>
			</tr>		  
			</table>
		</div>
		
		<div class="eas_settings" <?php echo $hideme; ?>>
		<h4>Other settings</h4>
		<table>
		  <tr>
			<td style="width:35%;"><label for="<?php echo $this->get_field_id('color_style'); ?>" title="select a colour style">Color style</label></td>
			<td style="width:25%;">
			<?php
			echo '<select name="' . $this->get_field_name('color_style') . '" id="' . $this->get_field_id('color_style') . '" title="select a colour style" >';
			foreach($eas_color_styles as $k => $v){
				echo '<option value="' . $v . '" ' . ($color_style == $v ? 'selected="selected"' : "") .  '>' . $k . '</option>';
			}
			echo '</select>';
			?>
			</td>
			<td style="width:40%;">&nbsp;</td>
		  </tr>
		  <tr>
			<td><label for="<?php echo $this->get_field_id('enable_ticker'); ?>" title="scroll the auctions.">Ticker animation</label> </td>
			<td><input id="<?php echo $this->get_field_id('enable_ticker'); ?>" type="checkbox"  name="<?php echo $this->get_field_name('enable_ticker'); ?>" value="1" <?php echo $enable_ticker == "1" ? 'checked="checked"' : ""; ?> title="scroll the auctions." /></td>
		    <td>&nbsp;</td>		  
		  </tr>
		  <tr>
			<td><label for="<?php echo $this->get_field_id('visible_items');?>" title="The no of feed items to be visible.">Visible items</label></td>
			<td><input id="<?php echo $this->get_field_id('visible_items');?>" name="<?php echo $this->get_field_name('visible_items'); ?>" type="text" value="<?php echo $visible_items; ?>" class="widefat" title="The no of feed items to be visible."/></td>
			<td>&nbsp;</td>
		  </tr>
		  <tr>
			<td><label for="<?php echo $this->get_field_id('ticker_speed');?>" title="Speed of the ticker in seconds">Ticker speed</label></td>
			<td><input id="<?php echo $this->get_field_id('ticker_speed');?>" name="<?php echo $this->get_field_name('ticker_speed'); ?>" type="text" value="<?php echo $ticker_speed; ?>" title="Speed of the ticker in seconds"/></td>
			<td>seconds</td>
		  </tr>
		</table>
		</div>
		<div class="eas_support">
		<img src="<?php echo WI_EAS_F_URL ?>images/eas-pro-logo-small.png" alt="EAS logo" title="If you like this plugin, consider making a small donation." style="height:36px;vertical-align:middle;margin-right:14px;" />
		<a href="http://facebook.com/webimpressions" class="eas_fblike" target="_blank">Like</a>
		<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=dave%40webimpressions%2eco%2euk&lc=GB&no_shipping=1&currency_code=GBP" target="_blank" class="eas_donatebtn">Donate</a>
		<a href="http://www.webimpressions.co.uk/wp/plugins/eas/" target="_blank">Support</a>		
		</div>		
		<?php	
	}
	
} //end widget class

function eas_scroller_init(){
	register_widget('eas_scroller_f_widget');
}

function eas_widget_scripts(){
	if(in_array($GLOBALS['pagenow'], array('widgets.php'))){
		?>
	<style type="text/css">
	
		.eas_text_button {
		font-size:inherit!important;
		background: url('<?php echo WI_EAS_F_URL;  ?>images/donate.png') left center no-repeat;
		margin:0!important;
		padding:0 10px 0 0!important;
		border:none!important;
	  appearance: none!important;
	  box-shadow: none!important;
	  border-radius: none!important;
	  cursor:pointer;
	  background-color:inherit!important;
	  width:74px;!important;
		}
		
		.eas_text_button:hover {
		outline:none!important;
		color:red;
		
		}
		.eas_settings table{
			width:100%;
			table-layout:fixed;
		}
	
	
		.eas_settings h4{
			border-bottom: 1px solid #DFDFDF;
			margin: 20px -11px 10px -11px;
			padding: 5px 11px 5px 11px;
			border-top: 1px solid #DFDFDF;
			background-color: #fff;
			background-image: -ms-linear-gradient(top,#fff,#f9f9f9);
			background-image: -moz-linear-gradient(top,#fff,#f9f9f9);
			background-image: -webkit-linear-gradient(top,#fff,#f9f9f9);
			background-image: linear-gradient(top,#fff,#f9f9f9);
		}
		.eas_smalltext{
			font-size: 11px;
			color: #666666;
		}
		.eas_support{
			border: 1px solid #DFDFDF;
			padding: 10px 13px;
			background: #F9F9F9;
			text-decoration: none;
			margin: 10px -13px;
		}
		.eas_support a{
			text-decoration: none;
			margin: 0 5px;
		}
		.eas_support a:hover{
			text-decoration: underline;
		}
		.eas_donatebtn{
			background: url('<?php echo WI_EAS_F_URL;  ?>images/donate.png') left center no-repeat;
			color: #f60;
			padding-left: 20px;
		}
		.eas_donatebtn:hover span{
			display: inline;
			padding:10px;
			margin: -15px 0px 0px -50px;
			position:absolute;
			background:#ffffff;
			border:1px solid #cccccc;
			box-shadow: 0px 2px 3px rgba(0, 0, 0, 0.09);
			border-radius: 5px;
		}
		.eas_donatebtn span{
			display: none;
		}

		.eas_fblike{
			background: url('<?php echo WI_EAS_F_URL;  ?>images/like-button.png') left center no-repeat;
			padding-left: 19px;
		}
		.eas_fblike span{
			display: none;
		}
		.eas_fblike:hover span{
			display: inline;
			padding:10px;
			margin: -15px 0px 0px -50px;
			position:absolute;
			background:#ffffff;
			border:1px solid #cccccc;
			box-shadow: 0px 2px 3px rgba(0, 0, 0, 0.09);
			border-radius: 5px;
		}
		.eas_note{
			border: 1px solid #FFD893;
			padding: 5px;
			background: #FFFEDA;
			margin: 10px 0 0;
			display: block;
		}
	</style>
	
	<script type="text/javascript">
		jQuery(document).ready(function(){
			var social = '<iframe src="<?php echo WI_EAS_F_URL;  ?>fb.html" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:110px; height:32px;" allowTransparency="true"></iframe>';
			jQuery('.eas_fblike').live('mouseenter', function(){
			if(jQuery('.eas_fblike span').length == 0)
					jQuery(this).prepend('<span>' + social + '</span>');
			});		
		});
	</script>
	
	<?php
	}
}

function getCats($ebay_app_id,$parent_cat=null) {	
	
	if(!$parent_cat) $parent_cat = -1; // doesn't work as a prop

	// -1: top, 11450: Clothing, Shoes & Accessories, 1059: Men's Clothing,
	$endpoint = 'http://open.api.ebay.com/Shopping?callname=GetCategoryInfo&appid='.$ebay_app_id	.'&version=675&siteid=0&CategoryID='.$parent_cat.'&IncludeSelector=ChildCategories';
	$responsexml = '';
	
	if( ini_get('allow_url_fopen') ) {
		$responsexml = @file_get_contents($endpoint);
		if($responsexml) {

			$xml = simplexml_load_string($responsexml);
			// remove top from list
			unset($xml->CategoryArray->Category[0]);
			return $xml->CategoryArray;
		}
		return;
	} else if(function_exists('curl_version')) {
		$curl = curl_init();
		if (is_resource($curl) === true) {
			$endpoint = 'http://open.api.ebay.com/shopping?';
			$headers = array(
					'X-EBAY-API-CALL-NAME: GetCategoryInfo',
					'X-EBAY-API-VERSION: 521',
					'X-EBAY-API-REQUEST-ENCODING: XML',
					'X-EBAY-API-SITE-ID: 0',
					'X-EBAY-API-APP-ID: '.$ebay_app_id,
					'Content-Type: text/xml;charset=utf-8'
			);
			$xmlrequest = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
				<GetCategoryInfoRequest xmlns=\"urn:ebay:apis:eBLBaseComponents\">
				  	<CategoryID>".$parent_cat."</CategoryID>
					<IncludeSelector>ChildCategories</IncludeSelector>
				</GetCategoryInfoRequest>";

			$session  = curl_init($endpoint);
			curl_setopt($session, CURLOPT_POST, true);
			curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($session, CURLOPT_POSTFIELDS, $xmlrequest);
			curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
			$responsexml = curl_exec($session);
			curl_close($session);

			$xml = simplexml_load_string($responsexml);
			// remove top from list
			unset($xml->CategoryArray->Category[0]);
			return $xml->CategoryArray;
		}
	} else {
		return;
	}
}

