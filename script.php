<?php

/*

SQL Structure

CREATE TABLE IF NOT EXISTS `pending_url` (
  `pending_url_id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(1024) NOT NULL,
  PRIMARY KEY (`pending_url_id`),
  KEY `url` (`url`(767))
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `recipe` (
  `recipe_id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(1024) NOT NULL,
  `image_url` text NOT NULL,
  `image_data` text NOT NULL,
  `title` text NOT NULL,
  `author` text NOT NULL,
  `from` text NOT NULL,
  `prep_time` text NOT NULL,
  `cooking_time` text NOT NULL,
  `serves` text NOT NULL,
  `raw` text NOT NULL,
  PRIMARY KEY (`recipe_id`),
  KEY `url` (`url`(767))
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `recipe_ingredient` (
  `recipe_ingredient_id` int(11) NOT NULL AUTO_INCREMENT,
  `recipe_id` int(11) NOT NULL,
  `ingredient` text NOT NULL,
  PRIMARY KEY (`recipe_ingredient_id`),
  KEY `recipe_id` (`recipe_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `recipe_step` (
  `recipe_step_id` int(11) NOT NULL AUTO_INCREMENT,
  `recipe_id` int(11) NOT NULL,
  `step` text NOT NULL,
  PRIMARY KEY (`recipe_step_id`),
  KEY `recipe_id` (`recipe_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

*/

	$conn = new mysqli('localhost','user','password','database');

	set_time_limit(0);

	Class BBCRecipe {
		public $url = "";
		public $imageUrl = "";
		public $imageData = "";
		public $raw = "";
		public $title = "";
		public $author = "";
		public $from = "";
		public $prepTime = "";
		public $cookingTime = "";
		public $serves = "";
		public $dietry = array();
		public $ingredients = array();
		public $steps = array();
	}

	$totalAllowedChecks = 1;
	$checksSoFar = 0;

	libxml_use_internal_errors(true);

	$baseUrl = 'http://www.definitelynotbbc.co.uk';

	$checkString = "/food/recipes/";
	$checkLength = strlen($checkString);

	$recipes = array();
	$pendingUrls = array();

	$sql = "SELECT * FROM `pending_url`";
	$result = $conn->query($sql) or die($conn->error);
	while($row = $result->fetch_assoc()){
		$pendingUrls[] = stripslashes($row['url']);
	}


	/*$pendingUrls = array(
		'/food/recipes/hearty_vegetable_soup_14365'
	);*/

	$checkedUrls = array();

	$sql = "SELECT * FROM `recipe`";
	$result = $conn->query($sql) or die($conn->error);
	while($row = $result->fetch_assoc()){
		$checkedUrls[] = stripslashes($row['url']);
	}


	while(count($pendingUrls) > 0 and $checksSoFar < $totalAllowedChecks)
	{
		echo $checksSoFar."/".$totalAllowedChecks." - Sleeping for 1 second then checking\n";
		sleep(1);
		$thisUrl = $pendingUrls[0];

		if(!in_array($thisUrl,$checkedUrls))
		{
			echo "CHECKING URL: ".$thisUrl."\n";
			$html = file_get_contents($baseUrl.$thisUrl);

			$dom = new DOMDocument;
			$dom->loadHTML($html);

			/*
			Scrape all recipe URLS from the site
			*/
			foreach ($dom->getElementsByTagName('a') as $tag) {

				if(substr($tag->getAttribute('href'),0,$checkLength) == $checkString){

					$newUrl = $tag->getAttribute('href');
					$tmp = explode("/",$newUrl);
					if(count($tmp) <> 4)
					{
						 echo " INVALID URL: ".$newUrl." does not appear to be a recipe URL\n";
					}
					elseif(in_array($newUrl,$checkedUrls))
					{
						echo "  CHECKED URL:" . $newUrl." already in checked List\n";	
					}
					elseif(in_array($newUrl, $pendingUrls))
					{
						echo "  PENDING URL:" . $newUrl." already in pending List\n";
					}
					else
					{
						echo "   ADDING URL: ".$newUrl."\n";
						$pendingUrls[] = $newUrl;
						$sql = "INSERT INTO `pending_url` (`pending_url_id`,`url`) VALUES ( NULL, '".$conn->escape_string($newUrl)."')";
						$conn->query($sql) or die($conn->error);
					}
				}

			}

			/*
			Scrape the recipe content
			*/
			$recipe = new BBCRecipe();
			$recipe->url = $thisUrl;
			$recipe->raw = $html;

			$finder = new DomXPath($dom);

			// Title
			$classname="content-title__text";
			$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
			foreach ($nodes as $node) {
				$recipe->title = $node->nodeValue;
			}

			// Image
			foreach ($dom->getElementsByTagName('img') as $tag) {
				if($tag->getAttribute('class')=='recipe-media__image responsive-images')
				{
					$recipe->imageUrl = $tag->getAttribute('src');
				}
			}
			if($recipe->imageUrl != "")
			{
				$recipe->imageData = base64_encode(file_get_contents($recipe->imageUrl));
			}

			// Author and From
			$classname="chef__link";
			$authorNode = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]")->item(0);
			$fromNode = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]")->item(1);
			if ($authorNode instanceof DomElement) {
			    $recipe->author = $authorNode->nodeValue;
			}
			if ($fromNode instanceof DomElement) {
			    $recipe->from = $fromNode->nodeValue;
			}

			// Prep Time
			$classname="recipe-metadata__prep-time";
			$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
			foreach ($nodes as $node) {
				$recipe->prepTime = $node->nodeValue;
			}

			// Cooking Time
			$classname="recipe-metadata__cook-time";
			$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
			foreach ($nodes as $node) {
				$recipe->cookingTime = $node->nodeValue;
			}

			// Serves
			$classname="recipe-metadata__serving";
			$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
			foreach ($nodes as $node) {
				$recipe->serves = $node->nodeValue;
			}

			// Dietry
			// TODO

			// Recipe Ingredients
			$classname="recipe-ingredients__list-item";
			$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
			foreach ($nodes as $node) {
				$recipe->ingredients[] = $node->nodeValue;
			}

			// Recipe Steps
			$classname="recipe-method__list-item-text";
			$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
			foreach ($nodes as $node) {
				$recipe->steps[] = $node->nodeValue;
			}

			//$recipes[] = $recipe;

			$sql = "INSERT INTO `recipe` (
				`recipe_id`,
				`url`,
				`image_url`,
				`image_data`,
				`title`,
				`author`,
				`from`,
				`prep_time`,
				`cooking_time`,
				`serves`,
				`raw`
			) VALUES (
				NULL,
				'".$conn->escape_string($recipe->url)."',
				'".$conn->escape_string($recipe->imageUrl)."',
				'".$conn->escape_string($recipe->imageData)."',
				'".$conn->escape_string($recipe->title)."',
				'".$conn->escape_string($recipe->author)."',
				'".$conn->escape_string($recipe->from)."',
				'".$conn->escape_string($recipe->prepTime)."',
				'".$conn->escape_string($recipe->cookingTime)."',
				'".$conn->escape_string($recipe->serves)."',
				'".$conn->escape_string($recipe->raw)."'
			)";
			$conn->query($sql) or die($conn->error);

			$recipe_id = $conn->insert_id;

			foreach($recipe->steps as $step)
			{
				$sql = "INSERT INTO `recipe_step` (
					`recipe_step_id`,
					`recipe_id`,
					`step`
				) VALUES (
					NULL,
					".intval($recipe_id).",
					'".$conn->escape_string($step)."'
				)";
				$conn->query($sql) or die($conn->error);
			}

			foreach($recipe->ingredients as $ingredient)
			{
				$sql = "INSERT INTO `recipe_ingredient` (
					`recipe_ingredient_id`,
					`recipe_id`,
					`ingredient`
				) VALUES (
					NULL,
					".intval($recipe_id).",
					'".$conn->escape_string($ingredient)."'
				)";
				$conn->query($sql) or die($conn->error);
			}

			$sql = "DELETE FROM `pending_url` WHERE `url` = '".$conn->escape_string($recipe->url)."' LIMIT 1";
			$conn->query($sql) or die($conn->error);

			/*

			*/
			$checkedUrls[] = $thisUrl;
			$checksSoFar++;
		}
		else
		{
			echo "  CHECKED URL:" . $thisUrl." already in checked List\n";	
		}
		array_splice($pendingUrls, 0, 1);
	}
