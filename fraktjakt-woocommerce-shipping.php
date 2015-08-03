<?php
/*
Plugin Name: Fraktjakt Shipping Method for WooCommerce
Plugin URI: http://www.fraktjakt.se
Description: Fraktjakt shipping method plugin for WooCommerce. Integrates several shipping services through Fraktjakt.
Version: 1.2.2
Author: Fraktjakt AB (Sweden)
Author URI: http://www.fraktjakt.se
*/

/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	function fraktjakt_shipping_method_init() {
		if ( ! class_exists( 'WC_Fraktjakt_Shipping_Method' ) ) {
			class WC_Fraktjakt_Shipping_Method extends WC_Shipping_Method {
				
				//$consignor_key;
				
				/**
				 * Constructor for your shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct() {
					$this->id                 = 'fraktjakt_shipping_method'; // Id for your shipping method. Should be uunique.
					$this->method_title       = __( 'Fraktjakt Shipping Method','fraktjakt-woocommerce-shipping' );  // Title shown in admin
					//$this->consignor_key = '000000000';
					$this->init();
				}

				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				function init() {
					// Load the settings API
					$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
					$this->init_settings(); // This is part of the settings API. Loads settings you previously init.
					
					$this->enabled	= isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
					$this->title	= isset( $this->settings['title'] ) ? $this->settings['title'] : 'Fraktjakt';
					$this->fee		= isset( $this->settings['fee'] ) ? $this->settings['fee'] : '';
					$this->test_mode			= isset( $this->settings['test_mode'] ) ? $this->settings['test_mode'] : 'production';
					$this->shipping_company_info	= isset( $this->settings['shipping_company_info'] ) ? $this->settings['shipping_company_info'] : 'no';
					$this->shipping_product_info	=  'yes'; //isset( $this->settings['shipping_product_info'] ) ? $this->settings['shipping_product_info'] : 'no';
					$this->distance_closest_delivery_info	= isset( $this->settings['distance_closest_delivery_info'] ) ? $this->settings['distance_closest_delivery_info'] : 'no';
					$this->estimated_delivery_info	= isset( $this->settings['estimated_delivery_info'] ) ? $this->settings['estimated_delivery_info'] : 'no';
					
					$this->fallback_service_name	= isset( $this->settings['fallback_service_name'] ) ? $this->settings['fallback_service_name'] : 'Fraktjakt';
					$this->fallback_service_price	= isset( $this->settings['fallback_service_price'] ) ? $this->settings['fallback_service_price'] :25.0;
					$this->dropoff_title	= isset( $this->settings['dropoff_title'] ) ? $this->settings['dropoff_title'] :"Home delivery";
				
					$this->fraktjakt_admin_email	= isset( $this->settings['fraktjakt_admin_email'] ) ? $this->settings['fraktjakt_admin_email'] :"";
				
					//$this->uri_query=($this->test_mode=='test')?'http://api2.fraktjakt.se/':'http://api1.fraktjakt.se/';
					//$this->consignor_id		= isset( $this->settings['consignor_id'] ) ? $this->settings['consignor_id'] : '';
					//$this->consignor_key	= isset( $this->settings['consignor_key'] ) ? $this->settings['consignor_key'] : '';
					
					if ($this->test_mode=='test') {
						$this->uri_query='http://api2.fraktjakt.se/';
						$this->consignor_id	= isset( $this->settings['consignor_id_test'] ) ? $this->settings['consignor_id_test'] : '';
						$this->consignor_key = isset( $this->settings['consignor_key_test'] ) ? $this->settings['consignor_key_test'] : '';
					}
					else {
						$this->uri_query='http://api1.fraktjakt.se/';
						$this->consignor_id	= isset( $this->settings['consignor_id'] ) ? $this->settings['consignor_id'] : '';
						$this->consignor_key = isset( $this->settings['consignor_key'] ) ? $this->settings['consignor_key'] : '';
					}
					
					$args = array(
						'post_type' => 'product',
						'posts_per_page' => '-1'
					);
					$product_query = new WP_Query( $args );
					$product_errors = 0;
					
					  if($product_query->have_posts()) {
					 	  $post_count = $product_query->post_count;
				      $posts = $product_query->posts;
				      $problem_products = array();
				        
				      for ($i = 0; $i < $post_count; $i++) {
				       	$product = new WC_Product( $posts[$i]->ID );
				        	
					      if($product->weight == '' || $product->weight <= 0)
						      {
								    array_push($problem_products, $posts[$i]);
								    $product_errors++;
					      }
					            
				      }
				        
				      add_action('admin_notices', function() use ($product_errors, $problem_products) {
				      	if ($product_errors > 0) {
				      		$class = "error";
						      $message = "<b>".__('Fraktjakt Shipping Method [WARNING]', 'fraktjakt-woocommerce-shipping')."</b><br>".$product_errors. __(' products are missing weight: ', 'fraktjakt-woocommerce-shipping');
							    echo"<div class=\"$class\"> <p>";
								  echo $message;
								  echo "<span style=\"font-size: 10px; line-height: 1;\">";
						      $links = "";
								  for ($i = 0; $i < 10; $i++) {
								    $links += edit_post_link($problem_products[$i]->post_name, '', ', ', $problem_products[$i]->ID);
								  }
	        				echo" etc.</span></p></div>";
				       	}					        
					    }, 2);
				        
				    }
				    
				    // Save settings in admin if you have any defined
					  add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ), 1 );
					
				}


				
	function init_form_fields() {
				global $woocommerce;

		$this->form_fields = array(
		    'enabled' => array(
				'title' => __('Enable/disable ', 'fraktjakt-woocommerce-shipping'),
				'type' => 'checkbox',
				'label' => __('Enable the Fraktjakt Shipping Method', 'fraktjakt-woocommerce-shipping'),
				'default' => ''
		    ),
		    'title' => array(
				'title' => __('Method Title', 'fraktjakt-woocommerce-shipping'),
				'type' => 'text',
				'description' => __('Enter the display title of the shipping method.', 'fraktjakt-woocommerce-shipping'),
				'default' => __('Fraktjakt', 'fraktjakt-woocommerce-shipping')
		    ),
			  'test_mode' => array(
				'title' => __('Operation Mode', 'fraktjakt-woocommerce-shipping'),
				'type' => 'select',
				'class'         => 'wc-enhanced-select',
				'description' => __('Test this shipping integration using Fraktjakts API TEST server before entering our production environment. <br />(Note: Requires a separate account in the <a href=http://api2.fraktjakt.se/account/register target=_blank>Fraktjakt TEST API server</a>)', 'fraktjakt-woocommerce-shipping'),
				//'label' => __('Enable test mode', 'fraktjakt-woocommerce-shipping'),
				'default' => 'production',
		        'options'    => array(
		          'production'    => __( 'Production', 'fraktjakt-woocommerce-shipping' ),
		          'test'   => __( 'Test', 'fraktjakt-woocommerce-shipping' ),
		        )
		    ),
		    'consignor_id' => array(
				'title' => __( 'Authentication', 'fraktjakt-woocommerce-shipping' ),
				'type' => 'text',
				'description'  => __( 'Consignor Id for the production server.', 'fraktjakt-woocommerce-shipping' ),
		    ),
		    'consignor_key' => array(
				//'title' => __('Consignor Key', 'fraktjakt-woocommerce-shipping'),
				'type' => 'text',
				'description' => __('Consignor Key for the production server.', 'fraktjakt-woocommerce-shipping'),
			  ),
			  'consignor_id_test' => array(
				//'title' => __( 'Authentication', 'fraktjakt-woocommerce-shipping' ),
				'type' => 'text',
				'description'  => __( 'Consignor Id for the test server.', 'fraktjakt-woocommerce-shipping' ),
		    ),
		    'consignor_key_test' => array(
				//'title' => __('Consignor Key', 'fraktjakt-woocommerce-shipping'),
				'type' => 'text',
				'description' => __('Consignor Key for the test server.', 'fraktjakt-woocommerce-shipping'),
			  ),
			  array(
				'title' => __('Shipping alternatives', 'fraktjakt-woocommerce-shipping'),
				'type' => 'title',
				'description' => __('Enable/disable display of the following attributes in the shipping alternatives that customers see in the cart and in checkout.', 'fraktjakt-woocommerce-shipping')
		    ),
			  'shipping_company_info' => array(
				//'title' => __('Shipping alternatives', 'fraktjakt-woocommerce-shipping'),
				'type' => 'checkbox',
				//'description' => __('Displayed in the shipping alternatives customers see in the cart and in checkout.', 'fraktjakt-woocommerce-shipping'),
				'label' => __('Display shipping company names', 'fraktjakt-woocommerce-shipping'),
				'default' => 'yes'				
		    ),
			  /*'shipping_product_info' => array(
				//'title' => __('Shipping products', 'fraktjakt-woocommerce-shipping'),
				'type' => 'checkbox',
				//'description' => __('Displayed in the shipping alternatives customers see in the cart and in checkout.', 'fraktjakt-woocommerce-shipping'),
				'label' => __('Display shipping product names', 'fraktjakt-woocommerce-shipping'),
				'default' => 'yes'
		    ),*/
			  'distance_closest_delivery_info' => array(
				//'title' => __('Agent', 'fraktjakt-woocommerce-shipping'),
				'type' => 'checkbox',
				//'description' => __('Displayed in the shipping alternatives customers see in the cart and in checkout.', 'fraktjakt-woocommerce-shipping'),
				'label' => __('Display Agent for package retrieval by the customer', 'fraktjakt-woocommerce-shipping'),
				'default' => 'yes'
		    ),
			  'dropoff_title' => array(
				//'title' => __('Door-to-Door delivery', 'fraktjakt-woocommerce-shipping'),
				'type' => 'text',
				'description' => __('Only shipping products which include Door-to-Door delivery will display this text.  <br>Displayed in the shipping alternatives customers see in the cart and in checkout.', 'fraktjakt-woocommerce-shipping'),
				'default' => __('Door-to-Door delivery', 'fraktjakt-woocommerce-shipping')
		    ),
			  'estimated_delivery_info' => array(
				//'title' => __('Delivery time', 'fraktjakt-woocommerce-shipping'),
				'type' => 'checkbox',
				//'description' => __('Displayed in the shipping alternatives customers see in the cart and in checkout.', 'fraktjakt-woocommerce-shipping'),
				'label' => __('Display Fraktjakts estimated delivery time info', 'fraktjakt-woocommerce-shipping'),
				'default' => 'yes'
		    ),
			  'fallback_service_name' => array(
				'title' => __('Fallback service', 'fraktjakt-woocommerce-shipping'),
				'type' => 'text',
				'description' => __('This text is shown together with a Fallback price when the webshop does not receive a prompt response from Fraktjakt, <br>for instance, when there is a communications problem over the internet.', 'fraktjakt-woocommerce-shipping'),
				'default' => __('Standard shipping', 'fraktjakt-woocommerce-shipping')
		    ),
			  'fallback_service_price' => array(
				//'title' => __('Fallback service price', 'fraktjakt-woocommerce-shipping'),
				'type' => 'text',
				'description' => __('The price that is shown together with the fallback text (above).', 'fraktjakt-woocommerce-shipping'),
				'default' => '100'
		    ),
			  'fraktjakt_admin_email' => array(
				'title' => __('Admin email address', 'fraktjakt-woocommerce-shipping'),
				'type' => 'text',
				'description' => __('Error messages from the Fraktjakt Shipping Method will be sent to this email address.', 'fraktjakt-woocommerce-shipping')
		    )			 
		);
	}
	
	/**
	 * Validate the consignor id and key
	 * @see validate_settings_fields()
	 */
	public function validate_consignor_id_field($key){
		$testmode = wp_kses_post( trim( stripslashes( $_POST[ $this->plugin_id . $this->id . '_' . 'test_mode' ] ) ) );
		//$uri=($testmode=='test')?'http://api2.fraktjakt.se/':'http://api1.fraktjakt.se/';
		if ($testmode == 'test') {
			$uri = 'http://api2.fraktjakt.se/';
			$consignor_id = wp_kses_post( trim( stripslashes( $_POST[ $this->plugin_id . $this->id . '_' . 'consignor_id_test' ] ) ) );
			$consignor_key = wp_kses_post( trim( stripslashes( $_POST[ $this->plugin_id . $this->id . '_' . 'consignor_key_test' ] ) ) );
		}
		else {
			$uri = 'http://api1.fraktjakt.se/';
			$consignor_id = wp_kses_post( trim( stripslashes( $_POST[ $this->plugin_id . $this->id . '_' . 'consignor_id' ] ) ) );
			$consignor_key = wp_kses_post( trim( stripslashes( $_POST[ $this->plugin_id . $this->id . '_' . 'consignor_key' ] ) ) );
		}
		
		if (($errmsg = test_connection($consignor_id, $consignor_key, $uri)) != "") {
			array_push($this->errors, $errmsg);
			return wp_kses_post( trim( stripslashes( $_POST[ $this->plugin_id . $this->id . '_' . $key ] ) ) );
		}
		else {
			return wp_kses_post( trim( stripslashes( $_POST[ $this->plugin_id . $this->id . '_' . $key ] ) ) );
		}
		
		
	}
	
	/**
	 * Display errors by overriding the display_errors() method
	 * @see display_errors()
	 */
	public function display_errors( ) {
	
		// loop through each error and display it
		foreach ( $this->errors as $key => $errmsg ) {
			$message = "<b>".__('Fraktjakt Shipping Method [ERROR]', 'fraktjakt-woocommerce-shipping')."</b><br>".$errmsg;
			$class = "error";
			echo "<div class=\"$class\"> <p>";
			echo $message;
			echo "</p></div>";
			
		}
		
	}

				


				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package ) {
					global $woocommerce;
					
					    $cart_items = $woocommerce->cart->get_cart();
						$cart_amount = floatval( preg_replace( '#[^\d.]#', '', $woocommerce->cart->get_cart_total() ) );
						
						
						
						$xml ='<?xml version="1.0" encoding="UTF-8"?>'."\r\n";
						$xml.='<shipment>'."\r\n";
						$xml.=  '<value>'.$cart_amount.'</value>'."\r\n";
						$xml.=  '<consignor>'."\r\n";
						$xml.=	'<id>'.$this->consignor_id.'</id>'."\r\n";
						$xml.=	'<key>'.$this->consignor_key.'</key>'."\r\n";
						$xml.=	' <currency>SEK</currency>'."\r\n";
						$xml.=   ' <language>sv</language>'."\r\n";
						$xml.=   ' <encoding>UTF-8</encoding>'."\r\n";
						$xml  .= '   <system_name>WooCommerce</system_name>'."\r\n";
						$xml  .= '   <module_version>1.1.0</module_version>'."\r\n";
						$xml  .= '   <api_version>2.91</api_version>'."\r\n";
						$xml.= '</consignor>'."\r\n";
						$xml.= ' <parcels>'."\r\n";
						foreach ($cart_items as $id => $cart_item) {
						  $_product = $cart_item['data'];
						  $quantity= $cart_item['quantity'];
         				  $weight = $_product->weight;
						  $length = $_product->length;
						  $width = $_product->width;
						  $height = $_product->height;
						 for($i=1;$i<=$quantity;$i++)
						 { 
							  if($weight!='')
							  {
								$xml.='  <parcel>'."\r\n";
								$xml.='  <weight>'.$weight.'</weight>'."\r\n";
								$xml.='  <length>'.$length.'</length>'."\r\n";
								$xml.='  <width>'.$width.'</width>'."\r\n";
								$xml.=' <height>'.$height.'</height>'."\r\n";
								$xml.='</parcel>'."\r\n";
							  }
						 }
						
					}
					$xml.='</parcels>'."\r\n";
					$xml.=' <address_to>'."\r\n";
					
					$package['destination']['address']=($package['destination']['address']=='')?'Test street':$package['destination']['address'];
					$xml.=' <street_address_1>'.$package['destination']['address'].'</street_address_1>'."\r\n";
					
					$xml.= ' <street_address_2>'.$package['destination']['address_2'].'</street_address_2>'."\r\n";
					$xml.= ' <postal_code>'.$package['destination']['postcode'].'</postal_code>'."\r\n";
					$xml.= '<city_name>'.$package['destination']['city'].'</city_name>'."\r\n";
					$xml.=' <residential>1</residential>'."\r\n";
					$xml.= ' <country_code>'.$package['destination']['country'].'</country_code>'."\r\n";
					$xml.=  '</address_to>'."\r\n";
					$xml.='</shipment>'. "\r\n";
					$hash = md5($xml);
					$array=$hash; 
					
					if($this->consignor_id!='' && $this->consignor_key!='' && $package['destination']['postcode']!='' && $package['destination']['country']!='')
					{	
						 $httpHeaders = array(
						  "Expect: ",
						  "Accept-Charset: UTF-8",
						  "Content-type: application/x-www-form-urlencoded"
						);
						$httpPostParams = array(
						  'md5_checksum' => md5($xml),
						  'xml' => utf8_encode($xml)
						);
						 if (is_array($httpPostParams)) {
						  foreach ($httpPostParams as $key => $value) {
							$postfields[$key] = $key .'='. urlencode($value);
						  }
						  $postfields = implode('&', $postfields);
						}
						
						$ch = curl_init($this->uri_query."fraktjakt/query_xml");
						curl_setopt($ch, CURLOPT_FAILONERROR, false); // fail on errors
						curl_setopt($ch, CURLOPT_FRESH_CONNECT, true); // forces a non-cached connection
						if ($httpHeaders) curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders); // set http headers
						curl_setopt($ch, CURLOPT_POST, true); // initialize post method
						curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields); // variables to post
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return into a variable
						curl_setopt($ch, CURLOPT_TIMEOUT, 30); // timeout after 30s
						$response = curl_exec($ch);
						curl_close($ch);
						$xml_data = simplexml_load_string( '<root>'.preg_replace( '/<\?xml.*\?>/', '', $response ).'</root>' );
						$array = json_decode(json_encode($xml_data), true);	
						//set_transient($hash, $array, 60 * 60 * 24 );
						if(is_array($array['shipment']))
						{
							
							$shipment_id=$array['shipment']['id'];
							foreach($array['shipment']['shipping_products']['shipping_product']	as $key=>$value)
							{
								$price=$total_price=$value['price'];
								$tax_class=$value['tax_class'];
								if($tax_class!='')
								{
									$total_price=$price+(($price/100)*$tax_class);
								}
								$description=$value['description'];
								$label="";
								
								
								$description_data=explode("-",$description);
								
								if($this->shipping_company_info=='yes')
								{
									$label.=$description_data[0];
								}
								else if ($value['id'] == 0) {
									$label.=$description_data[0];
								}
								if($this->shipping_product_info=='yes')
								{
									unset($description_data[0]);
									if($label!='' && $value['id'] != 0)
									{
										$label.=", ";		
									}
									$label.=implode(" - ",$description_data);
								}
								

								if($this->distance_closest_delivery_info=='yes')
								{
									if((!is_array($value['agent_link']) || !is_array($value['agent_info'])))
									{
										$label.='<br />';
										$label.='<span style=\"font-weight: 400;\">'.__( 'Agent','fraktjakt-woocommerce-shipping' ).'';
										$label.=':&nbsp;';
										if(!is_array($value['agent_link']) && !is_array($value['agent_info']))
										{
											$label.='<a href="'. $value['agent_link'] .'" target="_blank" style="color: #666666;">';
										}
										if(!is_array($value['agent_info']))
										{
											$label.=$value['agent_info'];
										}
										if(!is_array($value['agent_link']) && !is_array($value['agent_info']))
										{
											$label.='</a></span>';
										}
									}
									else
									{
											if ($this->dropoff_title != "") {
												$label.='<br />';
											}
											$label.="<span style=\"font-weight: 400;\">".$this->dropoff_title."</span>";
									}
									
								}
								if(!is_array($value['arrival_time']) && $this->estimated_delivery_info=='yes')
								{
										$label.='<br />';
										$label.='<span style=\"font-weight: 400;\">'.__( 'Arrival Time','fraktjakt-woocommerce-shipping' ).'';
										$label.=':&nbsp;';
										$label.=$value['arrival_time'];
										$label.="</span>";
									
								}
								$label.='<br><span style=\"font-weight: 400;\">'.__( 'Price','fraktjakt-woocommerce-shipping' ).'';
								
									$rate = array(
										'id' =>$this->id."_".$shipment_id."_".$value['id'],
										'label' => $label,
										'cost' => $total_price,
										'tax_class'=>$tax_class
									);
									
									$this->add_rate( $rate );
							}
						}
						else
						{
							if($this->fraktjakt_admin_email!='')
							{
								$message="";
								$message.="Code :".$array['shipment']['code']."<br>";
								$message.="Warning Code :".$array['shipment']['warning_message']."<br>";
								$message.="Error Message :".$array['shipment']['error_message']."<br>";
								$headers = array('Content-Type: text/html; charset=UTF-8');
								wp_mail($this->fraktjakt_admin_email, 'Error Message from Module', $message,$headers);
							}
							$rate = array(
								'id' =>$this->id,
								'label' =>$this->fallback_service_name,
								'cost' => $this->fallback_service_price,
								'tax_class'=>0
							);
							$this->add_rate($rate);
						}
								
					}
					
				}
			}
		}
	}



add_action( 'woocommerce_shipping_init', 'fraktjakt_shipping_method_init' );

function add_fraktjakt_shipping_method( $methods ) {
	$methods[] = 'WC_Fraktjakt_Shipping_Method';
	return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'add_fraktjakt_shipping_method' );

function consignor_admin_error_notice() {
	$class = "error";
	$message = "Cosignor id OR Cosignor key missing";
        echo"<div class=\"$class\"> <p>$message</p></div>"; 
}

function test_connection($consignor_id, $consignor_key, $server) {
	$xml ='<?xml version="1.0" encoding="UTF-8"?>'."\r\n";
	$xml.='<shipment>'."\r\n";
	$xml.=  '<value>199.50</value>'."\r\n";
	$xml.= '<shipper_info>1</shipper_info>'."\r\n";
	$xml.=  '<consignor>'."\r\n";
	$xml.=	'<id>'.$consignor_id.'</id>'."\r\n";
	$xml.=	'<key>'.$consignor_key.'</key>'."\r\n";
	$xml.=	' <currency>SEK</currency>'."\r\n";
	$xml.=   ' <language>sv</language>'."\r\n";
	$xml.=   ' <encoding>UTF-8</encoding>'."\r\n";
	$xml  .= '   <system_name>WooCommerce</system_name>'."\r\n";
	$xml  .= '   <module_version>1.1.0</module_version>'."\r\n";
	$xml  .= '   <api_version>2.91</api_version>'."\r\n";
	$xml.= '</consignor>'."\r\n";
	$xml.= ' <parcels>'."\r\n";
	$xml.= '<parcel>'."\r\n";
    $xml.= '<weight>2.8</weight>'."\r\n";
    $xml.= '<length>30</length>'."\r\n";
    $xml.= '<width>20</width>'."\r\n";
    $xml.= '<height>10</height>'."\r\n";
   	$xml.= '</parcel>'."\r\n";
	$xml.='</parcels>'."\r\n";
	$xml.=' <address_to>'."\r\n";
	$xml.=' <street_address_1>>Hedenstorp 10</street_address_1>'."\r\n";
	$xml.= ' <street_address_2></street_address_2>'."\r\n";
	$xml.= ' <postal_code>33292</postal_code>'."\r\n";
	$xml.= '<city_name>Gislaved</city_name>'."\r\n";
	$xml.=' <residential>1</residential>'."\r\n";
	$xml.= ' <country_code>SE</country_code>'."\r\n";
	$xml.=  '</address_to>'."\r\n";
	$xml.='</shipment>'. "\r\n";
	
	$httpHeaders = array(
	  "Expect: ",
	  "Accept-Charset: UTF-8",
	  "Content-type: application/x-www-form-urlencoded"
	);
	$httpPostParams = array(
	  'md5_checksum' => md5($xml),
	  'xml' => utf8_encode($xml)
	);
	 if (is_array($httpPostParams)) {
	  foreach ($httpPostParams as $key => $value) {
		$postfields[$key] = $key .'='. urlencode($value);
	  }
	  $postfields = implode('&', $postfields);
	}
	
	$ch = curl_init($server."fraktjakt/query_xml");
	curl_setopt($ch, CURLOPT_FAILONERROR, false); // fail on errors
	curl_setopt($ch, CURLOPT_FRESH_CONNECT, true); // forces a non-cached connection
	if ($httpHeaders) curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders); // set http headers
	curl_setopt($ch, CURLOPT_POST, true); // initialize post method
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields); // variables to post
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return into a variable
	curl_setopt($ch, CURLOPT_TIMEOUT, 30); // timeout after 30s
	$response = curl_exec($ch);
	curl_close($ch);
	$xml_data = simplexml_load_string( '<root>'.preg_replace( '/<\?xml.*\?>/', '', $response ).'</root>' );
	$array = json_decode(json_encode($xml_data), true);
	
	$message="";
	if(is_array($array['shipment'])) {
		if ($array['shipment']['code'] != 0) {
			//$message.="Code :".$array['shipment']['code']."<br>";
			//$message.="Warning Code :".$array['shipment']['warning_message']."<br>";
			//$message.="Error Message :".$array['shipment']['error_message']."<br>";
			$message.=$array['shipment']['error_message'];
		}
	}
	else {
		$message = "Unable to reach $server";
	}
	return $message;
}

function fraktjakt_shipping_complete($order_id){
	$order = new WC_Order( $order_id );
	$billing_email = get_post_meta( $order->id, '_billing_email',true );
	$billing_phone = get_post_meta( $order->id, '_billing_phone',true );
	$billing_first_name = get_post_meta( $order->id, '_billing_first_name',true );
	$billing_last_name = get_post_meta( $order->id, '_billing_last_name',true );
	
	
	$items = $order->get_items();
	$items_shipping = $order->get_items('shipping');
	foreach($items_shipping as $key=>$value)
	{
		$method=explode("_",$value['method_id']);
		$shipping_product_id=$method[count($method)-1];
		$shipment_id=$method[count($method)-2];
	}
		
	$fraktjakt_shipping_method_settings = get_option( 'woocommerce_fraktjakt_shipping_method_settings' );
	
	$testmode = $fraktjakt_shipping_method_settings['test_mode'];
	if ($testmode == 'test') {
		$uri_query = 'http://api2.fraktjakt.se/';
		$consignor_id = $fraktjakt_shipping_method_settings['consignor_id_test'];
		$consignor_key = $fraktjakt_shipping_method_settings['consignor_key_test'];
	}
	else {
		$uri_query = 'http://api1.fraktjakt.se/';
		$consignor_id = $fraktjakt_shipping_method_settings['consignor_id'];
		$consignor_key = $fraktjakt_shipping_method_settings['consignor_key'];
	}
		
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n";
		$xml.= '<OrderSpecification>' . "\r\n";
		$xml.= '  <consignor>' . "\r\n";
		$xml.= '    <id>'.$consignor_id.'</id>' . "\r\n";
		$xml.= '    <key>'.$consignor_key.'</key>' . "\r\n";
		$xml.= '    <currency>SEK</currency>' . "\r\n";
		$xml.= '    <language>sv</language>' . "\r\n";
		$xml.= '    <system_name>WooCommerce</system_name>'."\r\n";
		$xml.= '    <module_version>1.1.0</module_version>'."\r\n";
		$xml.= '    <api_version>2.91</api_version>'."\r\n";
		$xml.= '  </consignor>' . "\r\n";
		$xml.= '  <shipment_id>'. $shipment_id .'</shipment_id>' . "\r\n";
		$xml.= '  <shipping_product_id>'. $shipping_product_id .'</shipping_product_id>' . "\r\n";
		$xml.= '  <reference>Woocommerce order #'. $order->id .'</reference>' . "\r\n";
		$xml.= '  <commodities>' . "\r\n";		
		
    foreach ($items as $product) {

		$product_description = get_post($product['product_id'])->post_content;
		$description = ($product_description == '') ? $product['name'] : $product_description;

		$product_data = new WC_Product( $product['product_id'] );
		$xml .=  '    <commodity>' . "\r\n";
		$xml .= '      <name>'. $product['name'] .'</name>' . "\r\n";
		$xml .= '      <quantity>'. $product['qty'] .'</quantity>' . "\r\n";
		$xml .= '      <taric></taric>' . "\r\n";
		$xml .= '      <quantity_units>EA</quantity_units>' . "\r\n";
		$xml .= '      <description>'. $description .'</description>' . "\r\n";
		$xml .= '      <weight>'.$product_data->get_weight() .'</weight>' . "\r\n";
		$xml .= '      <unit_price>'. round($product['line_subtotal'], 2) .'</unit_price>' . "\r\n";
		$xml .= '    </commodity>' . "\r\n";
			
      }
	  $xml .=  '  </commodities>' . "\r\n";
	 
	  
		$xml .=  '  <recipient>' . "\r\n";
		$xml .=  '    <name_to>'.$billing_first_name.' '.$billing_last_name.'</name_to>' . "\r\n";
		$xml .=  '    <telephone_to>'.$billing_phone.'</telephone_to>' . "\r\n";
		$xml .=  '    <email_to>'.$billing_email.'</email_to>' . "\r\n";
		$xml .=  '  </recipient>' . "\r\n";
		
        $xml .=  ' </OrderSpecification>' . "\r\n";
		
			$httpHeaders = array(
			  "Expect: ",
			  "Accept-Charset: UTF-8",
			  "Content-type: application/x-www-form-urlencoded"
			);
			$httpPostParams = array(
			  'md5_checksum' => md5($xml),
			  'xml' => utf8_encode($xml)
			);
			if (is_array($httpPostParams)) {
			  foreach ($httpPostParams as $key => $value) {
				$postfields[$key] = $key .'='. urlencode($value);
			  }
			  $postfields = implode('&', $postfields);
			}
			$ch = curl_init($uri_query."orders/order_xml");
			curl_setopt($ch, CURLOPT_FAILONERROR, false); // fail on errors
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true); // forces a non-cached connection
			if ($httpHeaders) curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders); // set http headers
			curl_setopt($ch, CURLOPT_POST, true); // initialize post method
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields); // variables to post
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return into a variable
			curl_setopt($ch, CURLOPT_TIMEOUT, 30); // timeout after 30s
			$response = curl_exec($ch);
			curl_close($ch);

}
add_action( 'woocommerce_order_status_processing', 'fraktjakt_shipping_complete' );
	
	
	
load_plugin_textdomain('fraktjakt-woocommerce-shipping', false, 'fraktjakt-woocommerce-shipping/languages/');
	
	
}
?>