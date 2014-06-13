<?
class DOMTools{
	static function nodeHtml($node){
		$dom = new DOMDocument;
		if(get_class($node) == 'DOMDocument'){//DOMDocument is not a node, so get primary element in document
			$node =$dom->documentElement;
		}
		$dom->appendChild($dom->importNode($node,true));
		return $dom->saveHTML();
	}
	static function loadHtml($html){
		$dom = new DOMDocument;
		@$dom->loadHTML($html);
		$xpath = new DomXPath($dom);
		return array($dom,$xpath);
	}
}
