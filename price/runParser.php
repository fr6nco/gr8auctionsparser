#!/usr/bin/php
<?php

include('getAuctions.php');
include_once('simple_html_dom.php');

$con = mysqli_connect("localhost", "root", $argv[1], "gr8auctions");

if (mysqli_connect_errno()) {
	echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

echo("Going to LOAD all the auctions and prices\n");

//Forcing gettint of SK link, urls and details
$html = file_get_html('https://www.gr8auctions.eu/sk/bratislava/');


$lmenu = $html->find('div[id=menul]'); 

$auctions = array();

foreach($lmenu as $menu) {
	foreach($menu->find('a') as $link) {
		if (preg_match("/^https:\/\/www\.gr8auctions\.eu\/..\/(?<location>.+)\/(?<auction_id>auction_\d+)$/", $link->href, $matches)) {
			echo("Found auction\n");
			print_r($matches);

			$dat = $link->find('div[class=dat]');
			$cas = $link->find('div[class=cas]');
			$au = $link->find('div[class=au]');

			$date = $dat[0]->plaintext;
			$time = $cas[0]->plaintext;
			$auction_type = $au[0]->plaintext;

			$auction = array(
				'auction_type' => $auction_type,
				'enddate' => $date,
				'endtime' => $time,
				'location' => $matches['location'],
				'auction_id' => $matches['auction_id'],
				'link' => $link->href,
			);

			if(in_array($auction_type, array('e-aukcia', 'e-aukce', '@árverés', 'e-auction'))) {
				$sql = "SELECT * FROM auction WHERE gr8_id='". $matches['auction_id']."'";
				$result = $con->query($sql);

				if($result) {
					if($result->num_rows < 1) {
						//Create auction
						//date("Y-m-d H:i:s") mysql format
						//            [enddate] => 13.4.2016
						//            [endtime] => 13:00
						$date = DateTime::createFromFormat('d.m.Y G:i', $auction['enddate'] . ' ' . $auction['endtime']);
						$sql = "INSERT INTO auction (gr8_id, location, endtime, url) values ('" .$auction['auction_id']. "', '". $auction['location']. "', '". $date->format('Y-m-d H:i:s'). "', '". $auction['link']. "')";
						echo($sql. "\n");
						if ($con->query($sql) === FALSE) {
							echo "Error: " . $sql . " " . $con->error;
							die();
						}
			
						$auction['db_id'] = $con->insert_id;

					} else {
						if($result->num_rows > 1) die("Database returned more than 1 line with autcion id " .$matches['auction_id']);		
						while($row = $result->fetch_assoc()) {
    							$auction['db_id'] = $row['id'];
						}
					}
				} else {
					die("Error ". $con->error);
				}

				$auction_page = file_get_html($auction['link']);

				$table = $auction_page->find('table');

				$cars = getAuctions($table[0]);

				$auction['cars'] = $cars;
				
				foreach($cars as $gr8_id => $car) {
					echo("Gr8_ID ". $gr8_id. "\n");
					$sql = "SELECT * FROM car WHERE gr8_id='". $gr8_id ."'";
					$result = $con->query($sql);
					if($result) {
						if($result->num_rows < 1) {
							//CREATE new car entry
							$sql = "INSERT INTO car (gr8_id, url, img, details, name, auction_id) VALUES (".
							"'" . $gr8_id ."', ".
							"'" . $car['car_url'] ."', ".
							"'" . $car['img'] ."', ".
							"'" . $car['details'] ."', ".
							"'" . $car['description'] ."', ".
							"'" . $auction['db_id'] ."')";
					
							if ($con->query($sql) === TRUE) {
								$car['db_id'] = $con->insert_id;
							} else {
								die('Failed to insert '. $con->error);
							}
						}

						if($result->num_rows > 1) {
							die("MySQL query returned more cars with the given gr8_id ". $gr8_id);
						}
	
						if(!isset($car['db_id'])) {
							while($row = $result->fetch_assoc()) {
								$car['db_id'] = $row['id'];
							}
						}

						$sql = "SELECT max(price) as price, car_id FROM price_history WHERE car_id='". $car['db_id'] ."'";

						echo ($sql . "\n");

						$result = $con->query($sql);
						if ($result) {
							$tosave = FALSE;
							$db_price = 0;
							while($row = $result->fetch_assoc()) {
								$db_price = $row['price'];
							}

							if($db_price == NULL) {
								echo("No price history entries for this cas, so we are saving the price\n");
								$tosave = TRUE;
							} else {							

								if($db_price < $car['price']) {
									$tosave = TRUE;
									echo ("Price is higher, changed. saving for ". $car['description'] . "\n");
								} else {
									echo ("Price is the same, not saving\n");
								}
							}

							if($tosave) {
								$sql = "INSERT INTO price_history (car_id, price, currency, date_saved) VALUES (".
									"'" .$car['db_id'] ."', ".
									"'" .$car['price'] ."', ".
									"'" .$car['currency'] ."', ".
									"NOW()".
								")";

								echo($sql . "\n");
				
								if ($con->query($sql) !== TRUE) {
									die("Failed to insert price entry for car ". $con->error);
								}

							}
						} else {
							die("Could not get max price of car, ". $con->error);
						}



								
					} else {
						die("Select failed, ". $con->error);
					}
				}	

				$auctions[] = $auction;
			}
	
		}
	}
}


?>
