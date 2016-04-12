<?php

function getAuctions($table) {
	$auctions = array();
	foreach($table->find('tr') as $trr) {
		if (preg_match("/^d(?<id>\d+)$/",$trr->id, $matches)) {
			$id = $matches['id'];
	

			$iter = 0;
			foreach($trr->find('td') as $tdd) {
				
				switch($iter) {
					case 0:
						$img = $tdd->find('img');
						$imgurl = 'https://www.gr8auctions.eu' . $img[0]->attr['src'];
						$alt = $img[0]->attr['alt'];
						break;
					case 1:
						//nothin		
						break;

					case 2:
						$vozlnk = $tdd->find('a[class=vozlnk]');
						$carurl = $vozlnk[0]->href;
						$spandetails = $tdd->find('span');
						$cardetails = $spandetails[0]->plaintext;
						break;

					case 3:
						$pricespan = $tdd->getElementById("lc".$id);
						$price = $pricespan->innertext;
					
						$parts = explode(' ', $price);
						$currency = array_pop($parts);
						$price_val = implode('', $parts);

						break;
				}

				$iter++;
			}
	

			$pricespan = $trr->getElementById("veur". $id);
			
			$auctions[$id] = array(
				'img' => $imgurl,
				'description' => $alt,
				'details' => $cardetails,
				'car_url' => $carurl,
				'price' => $price_val,
				'currency' => $currency,
			);

		}
	}
	return $auctions;
}


?>
