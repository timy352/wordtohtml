<script>
MathJax = {
  loader: {load: ['[tex]/mathtools']},
  tex: {packages: {'[+]': ['mathtools']}},
};
</script>

<script type="text/javascript" id="MathJax-script" async
  src="https://cdn.jsdelivr.net/npm/mathjax@4/tex-mml-chtml.js">
</script>
<?php
class WordPHP // Version v2.1.15 - Timothy Edwards - 4 Nov 2026
{
	private $debug = false;
	private $file;
	private $rels_xml;
	private $head1_rels_xml;
	private $head2_rels_xml;
	private $head3_rels_xml;
	private $foot1_rels_xml;
	private $foot2_rels_xml;
	private $foot3_rels_xml;
	private $doc_xml;
	private $Icss;
	private $Tcss;
	private $Hcss;
	private $last = 'none';
	private $encoding = 'UTF-8';
	private $tmpDir = 'images';
	private $FSFactor = 22; //Font size conversion factor
	private $MTFactor = 13; //Margin and table width conversion factor
	private $Maths;
	private $Pagewidth; //Page width of sections - used for using correct width in defining position of text boxes
	private $EffPwidth; //Effective page width (after removing margins) - used for table width calcs.
	private $snt = 0;  //Section counter - used for using correct width in defining position of text boxes
	private $snti = 'N';   // find page size definition - used for using correct width in defining position of text boxes
	private $HF = '';
	private $Marwidth = array();
	private $Rstyle = array();
	private $anchor = array();

	
	/**
	 * CONSTRUCTOR
	 * 
	 * @param Boolean $debug Debug mode or not
	 * @param String $encoding selects alternative encoding if required
	 * @param String $tmpDir selects alternative image directory if required
	 * @return void
	 */
	public function __construct($debug_=null, $encoding=null, $tmpDir=null)
	{
		if($debug_ != null) {
			$this->debug = $debug_;
		}
		if ($encoding != null) {
			$this->encoding = $encoding;
		}
		if ($tmpDir != null) {
			$this->tmpDir = $tmpDir;
		}
	}


	/**
	 * READS The Document and Relationships into separated XML files
	 * 
	 * @param var $object The class variable to set as DOMDocument 
	 * @param var $xml The xml file
	 * @param string $encoding The encoding to be used
	 * @return void
	 */
	private function setXmlParts(&$object, $xml, $encoding)
	{
		$object = new DOMDocument();
		$object->encoding = $encoding;
		$object->preserveWhiteSpace = false;
		$object->formatOutput = true;
		$object->loadXML($xml);
		$object->saveXML();
	}



	/**
	 * READS The Document and Relationships into separated XML files
	 * 
	 * @param none
	 * @return void
	 */
	private function readZipPart()
	{
		$zip = new ZipArchive();
		$_xml = 'word/document.xml';
		$_xml_rels = 'word/_rels/document.xml.rels';
		
		if (true === $zip->open($this->file)) {
			//Get the main word document file
			if (($index = $zip->locateName($_xml)) !== false) {
				$xml = $zip->getFromIndex($index);
			}
			//Get the relationships
			if (($index = $zip->locateName($_xml_rels)) !== false) {
				$xml_rels = $zip->getFromIndex($index);
			}
			$zip->close();
		} else die('ERROR - non zip file');

		$enc = mb_detect_encoding($xml);
		$this->setXmlParts($this->doc_xml, $xml, $enc);
		$this->setXmlParts($this->rels_xml, $xml_rels, $enc);
		
		if($this->debug) {
			echo "XML File : word/document.xml<br>";
			echo "<textarea style='width:100%; height: 200px;'>";
			echo $this->doc_xml->saveXML();
			echo "</textarea>";
			echo "<br>XML File : word/_rels/document.xml.rels<br>";
			echo "<textarea style='width:100%; height: 200px;'>";
			echo $this->rels_xml->saveXML();
			echo "</textarea>";
		}
		
		$reader1 = new XMLReader();
		$reader1->XML($this->doc_xml->saveXML());
		$sn = 0;
		while ($reader1->read()) {
		// look for required page details
			if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:pgSz') { //Get page width
				$this->Pagewidth[$sn] = $reader1->getAttribute("w:w");
			}
			if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:pgMar') { //Get page width
				$Lmar = $reader1->getAttribute("w:left");
				$Rmar = $reader1->getAttribute("w:right");
				$this->Marwidth[$sn] = $Lmar + $Rmar;
				$this->EffPwidth[$sn] = $this->Pagewidth[$sn] - $this->Marwidth[$sn];
				++$sn;
			}
		}

	}


	/**
	 * Looks up a font in the themes XML file and returns the various theme fonts
	 * 
	 * @param - None
	 * @returns Array - The major and minor font of the theme
	 */
	private function findfonts()
	{
		$zip = new ZipArchive();
		$_xml_theme = 'word/theme/theme1.xml';
		if (true === $zip->open($this->file)) {
			//Get the style references from the word themes file
			if (($index = $zip->locateName($_xml_theme)) !== false) {
				$xml_theme = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		$enc = mb_detect_encoding($xml_theme);
		$this->setXmlParts($theme_xml, $xml_theme, $enc);
		if($this->debug) {
			echo "<br>XML File : word/theme/theme1.xml<br>";
			echo "<textarea style='width:100%; height: 200px;'>";
			echo $theme_xml->saveXML();
			echo "</textarea>";
		}

		$reader1 = new XMLReader();
		$reader1->XML($theme_xml->saveXML());
		while ($reader1->read()) {
		// look for required style
			if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'a:majorFont') {
				$st1 = new XMLReader;
				$st1->xml(trim($reader1->readOuterXML()));
				while ($st1->read()) {
					if ($st1->name == 'a:latin') {
						$Mfont['major'] = $st1->getAttribute("typeface");
						if (substr($Mfont['major'],0,9) == 'Helvetica'){
							$Mfont['major'] = 'Helvetica';
						}

					}
				}
			}
			if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'a:minorFont') {
				$st2 = new XMLReader;
				$st2->xml(trim($reader1->readOuterXML()));
				while ($st2->read()) {
					if ($st2->name == 'a:latin') {
						$Mfont['minor'] = $st2->getAttribute("typeface");
						if (substr($Mfont['minor'],0,9) == 'Helvetica'){
							$Mfont['minor'] = 'Helvetica';
						}
					}
				}
			}
		}
		return $Mfont;
	}


	/**
	 * Looks up the headers XML file and returns the headers if any exist
	 * 
	 * @param - None
	 * @returns String - The footnote number and associated text
	 */
	private function headers($Hfoot)
	{
		$Htext = array();
		$zip = new ZipArchive();
		$_xml_head1 = 'word/header1.xml';
		if (true === $zip->open($this->file)) {
			//Get the headers from the word header1 file
			if (($index = $zip->locateName($_xml_head1)) !== false) {
				$xml_head1 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		if (isset($xml_head1)){ // if the header1.xml file exists parse it
			$enc = mb_detect_encoding($_xml_head1);
			$this->setXmlParts($head1_xml, $xml_head1, $enc);
			if($this->debug) {
				echo "<br>XML File : word/header1.xml<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $head1_xml->saveXML();
				echo "</textarea>";
			}
		}

		$zip = new ZipArchive();
		$_xml_rels_head1 = 'word/_rels/header1.xml.rels';
		if (true === $zip->open($this->file)) {
			//Get the headers from the word header1 relationships file
			if (($index = $zip->locateName($_xml_rels_head1)) !== false) {
				$xml_rels_head1 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		if (isset($xml_rels_head1)){ // if the _rels/header1.xml.rels file exists parse it
			$enc = mb_detect_encoding($_xml_rels_head1);
			$this->setXmlParts($this->head1_rels_xml, $xml_rels_head1, $enc);
			if($this->debug) {
				echo "<br>XML File : word/_rels/header1.xml.rels<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $this->head1_rels_xml->saveXML();
				echo "</textarea>";
			}
		}
		$zip = new ZipArchive();
		$_xml_head2 = 'word/header2.xml';
		if (true === $zip->open($this->file)) {
			//Get the headers from the word header2 file
			if (($index = $zip->locateName($_xml_head2)) !== false) {
				$xml_head2 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		if (isset($xml_head2)){ // if the header2.xml file exists parse it
			$enc = mb_detect_encoding($_xml_head2);
			$this->setXmlParts($head2_xml, $xml_head2, $enc);
			if($this->debug) {
				echo "<br>XML File : word/header2.xml<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $head2_xml->saveXML();
				echo "</textarea>";
			}
		}

		$zip = new ZipArchive();
		$_xml_rels_head2 = 'word/_rels/header2.xml.rels';
		if (true === $zip->open($this->file)) {
			//Get the headers from the word header2 relationships file
			if (($index = $zip->locateName($_xml_rels_head2)) !== false) {
				$xml_rels_head2 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		if (isset($xml_rels_head2)){ // if the _rels/header2.xml.rels file exists parse it
			$enc = mb_detect_encoding($_xml_rels_head2);
			$this->setXmlParts($this->head2_rels_xml, $xml_rels_head2, $enc);
			if($this->debug) {
				echo "<br>XML File : word/_rels/header2.xml.rels<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $this->head2_rels_xml->saveXML();
				echo "</textarea>";
			}
		}

		$zip = new ZipArchive();
		$_xml_head3 = 'word/header3.xml';
		if (true === $zip->open($this->file)) {
			//Get the headers from the word header3 file
			if (($index = $zip->locateName($_xml_head3)) !== false) {
				$xml_head3 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		if (isset($xml_head3)){ // if the header3.xml file exists parse it
			$enc = mb_detect_encoding($_xml_head3);
			$this->setXmlParts($head3_xml, $xml_head3, $enc);
			if($this->debug) {
				echo "<br>XML File : word/header3.xml<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $head3_xml->saveXML();
				echo "</textarea>";
			}
		}
		
		$zip = new ZipArchive();
		$_xml_rels_head3 = 'word/_rels/header3.xml.rels';
		if (true === $zip->open($this->file)) {
			//Get the headers from the word header3 relationships file
			if (($index = $zip->locateName($_xml_rels_head3)) !== false) {
				$xml_rels_head3 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		if (isset($xml_rels_head3)){ // if the _rels/header3.xml.rels file exists parse it
			$enc = mb_detect_encoding($_xml_rels_head3);
			$this->setXmlParts($this->head3_rels_xml, $xml_rels_head3, $enc);
			if($this->debug) {
				echo "<br>XML File : word/_rels/header3.xml.rels<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $this->head3_rels_xml->saveXML();
				echo "</textarea>";
			}
		}

		$reader1 = new XMLReader();
		$Pelement = array();
		$Ttext = array();
		$Ttext[0] = $Halign ='';
		$h = 0;
		$zst = array();
		$zst[0] = '';
		$zstc = 1;
		if (isset($xml_head1) AND $Hfoot <> 'N'){
			if ($Hfoot == 'F'){
				if (isset($xml_head3)){ 
					$reader1->XML($head3_xml->saveXML());
					$this->HF = 'H3';
				} else {
					$reader1->XML($head1_xml->saveXML());
					$this->HF = 'H1';
				}
			} else if ($Hfoot == 'E'){
				$reader1->XML($head1_xml->saveXML());				
					$this->HF = 'H1';
			} else {
				if (isset($xml_head2)){
					$reader1->XML($head2_xml->saveXML());
					$this->HF = 'H2';
				} else {
					$reader1->XML($head1_xml->saveXML());
					$this->HF = 'H1';
				}
			}
			while ($reader1->read()) {
			// look for required style
				if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:jc') { //Get header alignment
					$Halign = $reader1->getAttribute("w:val");
				}
				if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:r') {

					$Pelement = $this->checkFormating($reader1,'',''); // Get inline style and associated text
					if (!isset($Pelement['style'])){
						$Pelement['style'] = '';
					}	
					$zst[$zstc] = $Pelement['style'];
					if ($zst[$zstc] != $zst[$zstc-1]){
						if ($zstc > 1){
							$Ttext[$h] .= "</span>".$Pelement['style'];
						} else {
							$Ttext[$h] .= $Pelement['style'];
						}
						$zstc++;
					}
					$Ttext[$h] .= $Pelement['text'];
				}
				if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:ptab') {
					++$h;
					$zst = array();
					$zst[0] = '';
					$zstc = 1;
				}
				if ($reader1->nodeType == XMLREADER::END_ELEMENT && $reader1->name == 'w:p') {
					if($h == 0){
						if ($Halign == ''){
							$Halign = 'left';
						}
						$Htext = "<table width='100%'><tr><td style = 'text-align:".$Halign.";'>".$Ttext[0]."</td></tr></table>";
					} else {
						$Htext = "<table width='100%'><tr><td style='text-align:left; '>".$Ttext[0]."</td> <td style='text-align:center;' >".$Ttext[1]."</td> <td style='text-align:right; '>".$Ttext[2]."</td></tr></table>";
					}
				}
			}
			$this->HF = '';
			return $Htext;
		}
	}



	/**
	 * Looks up the footers XML file and returns the footers if any exist
	 * 
	 * @param - None
	 * @returns String - The footnote number and associated text
	 */
	private function footers($Hfoot)
	{
		$zip = new ZipArchive();
		$_xml_foot1 = 'word/footer1.xml';
		if (true === $zip->open($this->file)) {
			//Get the footers from the word footer1 file
			if (($index = $zip->locateName($_xml_foot1)) !== false) {
				$xml_foot1 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		$F1text = array();
		if (isset($xml_foot1)){ // if the footer1.xml file exists parse it
			$enc = mb_detect_encoding($_xml_foot1);
			$this->setXmlParts($foot1_xml, $xml_foot1, $enc);
			if($this->debug) {
				echo "<br>XML File : word/footer1.xml<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $foot1_xml->saveXML();
				echo "</textarea>";
			}
		}

		$zip = new ZipArchive();
		$_xml_rels_foot1 = 'word/_rels/footer1.xml.rels';
		if (true === $zip->open($this->file)) {
			//Get the headers from the word footer1 relationships file
			if (($index = $zip->locateName($_xml_rels_foot1)) !== false) {
				$xml_rels_foot1 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		if (isset($xml_rels_foot1)){ // if the _rels/footer1.xml.rels file exists parse it
			$enc = mb_detect_encoding($_xml_rels_foot1);
			$this->setXmlParts($this->foot1_rels_xml, $xml_rels_foot1, $enc);
			if($this->debug) {
				echo "<br>XML File : word/_rels/footer1.xml.rels<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $this->foot1_rels_xml->saveXML();
				echo "</textarea>";
			}
		}
		
		$zip = new ZipArchive();
		$_xml_foot2 = 'word/footer2.xml';
		if (true === $zip->open($this->file)) {
			//Get the footers from the word footer2 file
			if (($index = $zip->locateName($_xml_foot2)) !== false) {
				$xml_foot2 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		$F2text = array();
		if (isset($xml_foot2)){ // if the footer2.xml file exists parse it
			$enc = mb_detect_encoding($_xml_foot2);
			$this->setXmlParts($foot2_xml, $xml_foot2, $enc);
			if($this->debug) {
				echo "<br>XML File : word/footer2.xml<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $foot2_xml->saveXML();
				echo "</textarea>";
			}
		}

		$zip = new ZipArchive();
		$_xml_rels_foot2 = 'word/_rels/footer2.xml.rels';
		if (true === $zip->open($this->file)) {
			//Get the headers from the word footer2 relationships file
			if (($index = $zip->locateName($_xml_rels_foot2)) !== false) {
				$xml_rels_foot2 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		if (isset($xml_rels_foot2)){ // if the _rels/footer2.xml.rels file exists parse it
			$enc = mb_detect_encoding($_xml_rels_foot2);
			$this->setXmlParts($this->foot2_rels_xml, $xml_rels_foot2, $enc);
			if($this->debug) {
				echo "<br>XML File : word/_rels/footer2.xml.rels<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $this->foot2_rels_xml->saveXML();
				echo "</textarea>";
			}
		}
		
		$zip = new ZipArchive();
		$_xml_foot3 = 'word/footer3.xml';
		if (true === $zip->open($this->file)) {
			//Get the footers from the word footer3 file
			if (($index = $zip->locateName($_xml_foot3)) !== false) {
				$xml_foot3 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		$F3text = array();
		if (isset($xml_foot3)){ // if the footer1.xml file exists parse it
			$enc = mb_detect_encoding($_xml_foot3);
			$this->setXmlParts($foot3_xml, $xml_foot3, $enc);
			if($this->debug) {
				echo "<br>XML File : word/footer3.xml<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $foot3_xml->saveXML();
				echo "</textarea>";
			}
		}
		
		$zip = new ZipArchive();
		$_xml_rels_foot3 = 'word/_rels/footer3.xml.rels';
		if (true === $zip->open($this->file)) {
			//Get the headers from the word footer3 relationships file
			if (($index = $zip->locateName($_xml_rels_foot3)) !== false) {
				$xml_rels_foot3 = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		if (isset($xml_rels_foot3)){ // if the _rels/footer3.xml.rels file exists parse it
			$enc = mb_detect_encoding($_xml_rels_foot3);
			$this->setXmlParts($this->foot3_rels_xml, $xml_rels_foot3, $enc);
			if($this->debug) {
				echo "<br>XML File : word/_rels/footer3.xml.rels<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $this->foot3_rels_xml->saveXML();
				echo "</textarea>";
			}
		}
		
		$reader1 = new XMLReader();
		$Pelement = array();
		$Ttext = array();
		$Ttext[0] = '';
		$h = 0;
		$zst = array();
		$zst[0] = $Falign = '';
		$zstc = 1;
		if (isset($xml_foot1) AND $Hfoot <> 'N'){ 
			if ($Hfoot == 'F'){
				if (isset($xml_foot3)){ 
					$reader1->XML($foot3_xml->saveXML());
					$this->HF = 'F3';
				} else {
					$reader1->XML($foot1_xml->saveXML());
					$this->HF = 'F1';
				}
			} else if ($Hfoot == 'E'){
				$reader1->XML($foot1_xml->saveXML());
					$this->HF = 'F1';
			} else {
				if (isset($xml_foot2)){
					$reader1->XML($foot2_xml->saveXML());
					$this->HF = 'F2';
				} else {
					$reader1->XML($foot1_xml->saveXML());
					$this->HF = 'F1';
				}
			}
			while ($reader1->read()) {
			// look for required style
				if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:jc') { //Get header alignment
					$Falign = $reader1->getAttribute("w:val");
				}
				if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:r') {

					$Pelement = $this->checkFormating($reader1,'',''); // Get inline style and associated text
					if (!isset($Pelement['style'])){
						$Pelement['style'] = '';
					}	
					$zst[$zstc] = $Pelement['style'];
					if ($zst[$zstc] != $zst[$zstc-1]){
						if ($zstc > 1){
							$Ttext[$h] .= "</span>".$Pelement['style'];
						} else {
							$Ttext[$h] .= $Pelement['style'];
						}
						$zstc++;
					}
					$Ttext[$h] .= $Pelement['text'];
				}
				if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:ptab') {
					++$h;
					$zst = array();
					$zst[0] = '';
					$zstc = 1;
				}
				if ($reader1->nodeType == XMLREADER::END_ELEMENT && $reader1->name == 'w:p') {
					if($h == 0){
						if ($Falign == ''){
							$Falign = 'left';
						}
						$Ftext = "<table width='100%'><tr><td style = 'text-align:".$Falign.";'>".$Ttext[0]."</td></tr></table>";
					} else {
						$Ftext = "<table width='100%'><tr><td style='text-align:left; '>".$Ttext[0]."</td> <td style='text-align:center;' >".$Ttext[1]."</td> <td style='text-align:right; '>".$Ttext[2]."</td></tr></table>";
					}
				}
			}
			$this->HF = '';
			return $Ftext;
		}
	}




	/**
	 * Looks up the footnotes XML file and returns the footnotes if any exist
	 * 
	 * @param - None
	 * @returns String - The footnote number and associated text
	 */
	private function footnotes()
	{
		$zip = new ZipArchive();
		$_xml_foot = 'word/footnotes.xml';
		if (true === $zip->open($this->file)) {
			//Get the footnotes from the word footnotes file file
			if (($index = $zip->locateName($_xml_foot)) !== false) {
				$xml_foot = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		$Ftext = array();
		if (isset($xml_foot)){ // if the footnotes.xml file exists parse it
			$enc = mb_detect_encoding($_xml_foot);
			$this->setXmlParts($foot_xml, $xml_foot, $enc);
			if($this->debug) {
				echo "<br>XML File : word/footnotes.xml<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $foot_xml->saveXML();
				echo "</textarea>";
			}
			$reader1 = new XMLReader();
			$reader1->XML($foot_xml->saveXML());
			while ($reader1->read()) {
			// look for required style
				$znum = 1;
				if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:footnote') { //Get footnote
					$zst = array();
					$zst[0] = '';
					$zstc = 1;
					$Footnum = $reader1->getAttribute("w:id");
					$Ftext[$Footnum] = '';
					$st2 = new XMLReader;
					$st2->xml(trim($reader1->readOuterXML()));
					while ($st2->read()) {
						if ($st2->nodeType == XMLREADER::ELEMENT && $st2->name == 'w:hyperlink') {
							$hyperlink = $this->getHyperlink($st2,'F'); // Add in hyperlinks in footnotes
							$Ftext[$Footnum] .= $hyperlink['open'];
							$Pelement2 = $this->checkFormating($st2,'','');
							$zst[$zstc] = $Pelement2['style'];
							if ($zst[$zstc] != $zst[$zstc-1]){
								if ($zstc > 1){
									$Ftext[$Footnum] .= "</span>".$Pelement2['style'];
								} else {
									$Ftext[$Footnum] .= $Pelement2['style'];
								}
								$zstc++;
							}
							$Ftext[$Footnum] .= $Pelement2['text'];
							$Ftext[$Footnum] .= $hyperlink['close'];
							$st2->next();
						}
						if ($st2->nodeType == XMLREADER::ELEMENT && $st2->name == 'w:br') {
							$Ftext[$Footnum] .= "<br>";
						}
						if ($st2->nodeType == XMLREADER::ELEMENT && $st2->name == 'w:r') {
			
							$Pelement = $this->checkFormating($st2,'',''); // Get inline style and associated text
							if (!isset($Pelement['style'])){
								$Pelement['style'] = '';
							}	
							$zst[$zstc] = $Pelement['style'];
							if ($zst[$zstc] != $zst[$zstc-1]){
								if ($zstc > 1){
									$Ftext[$Footnum] .= "</span>".$Pelement['style'];
								} else {
									$Ftext[$Footnum] .= $Pelement['style'];
								}
								$zstc++;
							}
							$Ftext[$Footnum] .= $Pelement['text'];
						}
					}
				}
			}
		}
		return $Ftext;
	}


	/**
	 * Looks up the endnotes XML file and returns the endnotes if any exist
	 * 
	 * @param - None
	 * @returns String - The endnote number and associated text
	 */
	private function endnotes()
	{
		$zip = new ZipArchive();
		$_xml_end = 'word/endnotes.xml';
		if (true === $zip->open($this->file)) {
			//Get the endnotes from the endnotes file
			if (($index = $zip->locateName($_xml_end)) !== false) {
				$xml_end = $zip->getFromIndex($index);
			}
			$zip->close();
		}
		$Etext = array();
		if (isset($xml_end)){ // if the endnotes.xml file exists parse it
			$enc = mb_detect_encoding($_xml_end);
			$this->setXmlParts($end_xml, $xml_end, $enc);
			if($this->debug) {
				echo "<br>XML File : word/endnotes.xml<br>";
				echo "<textarea style='width:100%; height: 200px;'>";
				echo $end_xml->saveXML();
				echo "</textarea>";
			}
		
			$reader1 = new XMLReader();
			$reader1->XML($end_xml->saveXML());
			while ($reader1->read()) {
			// look for required style
				$znum = 1;
				if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:endnote') { //Get endnote
					$zst = array();
					$zst[0] = '';
					$zstc = 1;
					$Endnum = $reader1->getAttribute("w:id");
					$Etext[$Endnum] = '';
					$st2 = new XMLReader;
					$st2->xml(trim($reader1->readOuterXML()));
					while ($st2->read()) {
						if ($st2->nodeType == XMLREADER::ELEMENT && $st2->name == 'w:hyperlink') {
							$hyperlink = $this->getHyperlink($st2,'E'); // Add in hyperlinks in endnotes
							$Etext[$Endnum] .= $hyperlink['open'];
							$Pelement2 = $this->checkFormating($st2,'','');
							$zst[$zstc] = $Pelement2['style'];
							if ($zst[$zstc] != $zst[$zstc-1]){
								if ($zstc > 1){
									$Etext[$Endnum] .= "</span>".$Pelement2['style'];
								} else {
									$Etext[$Endnum] .= $Pelement2['style'];
								}
								$zstc++;
							}
							$Etext[$Endnum] .= $Pelement2['text'];
							$Etext[$Endnum] .= $hyperlink['close'];
							$st2->next();
						}
						if ($st2->nodeType == XMLREADER::ELEMENT && $st2->name == 'w:br') {
							$Etext[$Endnum] .= "<br>";
						}
						if ($st2->nodeType == XMLREADER::ELEMENT && $st2->name == 'w:r') {
						
							$Pelement = $this->checkFormating($st2,'',''); // Get inline style and associated text				
							$zst[$zstc] = $Pelement['style'];
							if ($zst[$zstc] != $zst[$zstc-1]){
								if ($zstc > 1){
									$Etext[$Endnum] .= "</span>".$Pelement['style'];
								} else {
									$Etext[$Endnum] .= $Pelement['style'];
								}
								$zstc++;
							}
							$Etext[$Endnum] .= $Pelement['text'];
						}
					}
				}
			}
		}
		return $Etext;
	}


	/**
	 * Looks up the styles in the styles XML file and sets the parameters for all the styles
	 * 
	 * @param - None
	 * @return void
	 */
	private function findstyles()
	{
		$zip = new ZipArchive();
		$_xml_styles = 'word/styles.xml';
		if (true === $zip->open($this->file)) {
			//Get the style references from the word styles file
			if (($index = $zip->locateName($_xml_styles)) !== false) {
				$xml_styles = $zip->getFromIndex($index);
			}
			$zip->close();
		}

		$enc = mb_detect_encoding($xml_styles);
		$this->setXmlParts($styles_xml, $xml_styles, $enc);
		if($this->debug) {
			echo "<br>XML File : word/styles.xml<br>";
			echo "<textarea style='width:100%; height: 200px;'>";
			echo $styles_xml->saveXML();
			echo "</textarea>";
		}

		$Rfont = $this->findfonts();
		$reader1 = new XMLReader();
		$reader1->XML($styles_xml->saveXML());
		$FontTheme = '';
		$Rstyle = array();
		while ($reader1->read()) {
		// get all styles
			$znum = 1;
			if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:docDefaults') { //Get document default settings
				$st2 = new XMLReader;
				$st2->xml(trim($reader1->readOuterXML()));
				while ($st2->read()) {
					if($st2->name == "w:spacing") { // Checks for paragraph spacing
						if ($st2->getAttribute("w:before") <>''){
							$Rstyle['Default']['Mtop'] =  " margin-top: ".round($st2->getAttribute("w:before")/$this->MTFactor)."px;";
						}
						if ($st2->getAttribute("w:after") <>''){
							$Rstyle['Default']['Mbot'] =  " margin-bottom: ".round($st2->getAttribute("w:after")/$this->MTFactor)."px;";
						}
					}
					if($st2->name == "w:sz") { //Default font size
						$Rstyle['Default']['FontS'] = round($st2->getAttribute("w:val")/$this->FSFactor,2);
					}
					if($st2->name == "w:color") { // default font color
						$Rstyle['Default']['Color'] = $st2->getAttribute("w:val");
					}
					if($st2->name == "w:rFonts" and $st2->getAttribute("w:ascii")) { // Default font
						$DF = $st2->getAttribute("w:ascii");
						if (substr($DF,0,9) == 'Helvetica'){
							$DF = 'Helvetica';
						}
						$Rstyle['Default']['Font'] = " font-family: ".$DF.";";
					}
				}
			}
			if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:style') { //Get style settings
				$Fstyle = $reader1->getAttribute("w:styleId");
					$st1 = new XMLReader;
					$st1->xml(trim($reader1->readOuterXML()));
					while ($st1->read()) {

						if($st1->name == "w:rFonts" and $st1->getAttribute("w:ascii")) {
							$FF = $st1->getAttribute("w:ascii");
							if (substr($FF,0,9) == 'Helvetica'){
								$FF = 'Helvetica';
							}
							$Rstyle[$Fstyle]['Font'] = " font-family: ".$FF.";";
						}
						if($st1->name == "w:rFonts" and $st1->getAttribute("w:asciiTheme")) {
							$FontTheme = $st1->getAttribute("w:asciiTheme");
						}						
						if($st1->name == "w:sz") {
							$Rstyle[$Fstyle]['FontS'] = round($st1->getAttribute("w:val")/$this->FSFactor,2);
						}
						if($st1->name == "w:caps") {
							$Rstyle[$Fstyle]['Caps'] = " text-transform: uppercase;";
						}
						if($st1->name == "w:b") {
							$Rstyle[$Fstyle]['Bold'] = " font-weight: bold;";
						}
						if($st1->name == "w:u") {
							$Rstyle[$Fstyle]['Under'] = " text-decoration: underline;";
						}
						if($st1->name == "w:i") {
							$Rstyle[$Fstyle]['Italic'] = " font-style: italic;";
						}
						if($st1->name == "w:color") {
							$Rstyle[$Fstyle]['Color'] = $st1->getAttribute("w:val");
						}
						if($st1->name == "w:numId") { // Get paragraph numbering ref
							$Rstyle[$Fstyle]['parnum'] = $st1->getAttribute("w:val");
						}
						if($st1->name == "w:spacing") { // Checks for paragraph spacing
							if ($st1->getAttribute("w:before") <>''){
								$Rstyle[$Fstyle]['MPtop'] =  "margin-top: ".round($st1->getAttribute("w:before")/$this->MTFactor)."px;";
							}
							if ($st1->getAttribute("w:after") <>''){
								$Rstyle[$Fstyle]['MPbot'] =  "margin-bottom: ".round($st1->getAttribute("w:after")/$this->MTFactor)."px;";
							}
						}
						if($st1->name == "w:ind") { // Checks for paragragh indent
							if ($st1->getAttribute("w:left") <>''){
								$Rstyle[$Fstyle]['Ileft'] =  "padding-left: ".round($st1->getAttribute("w:left")/$this->MTFactor)."px;";
							}
							if ($st1->getAttribute("w:right") <>''){
								$Rstyle[$Fstyle]['Iright'] =  "padding-right: ".round($st1->getAttribute("w:right")/$this->MTFactor)."px;";
							}
							if ($st1->getAttribute("w:hanging") <>''){
								$Rstyle[$Fstyle]['Ihang'] =  "text-indent: -".round($st1->getAttribute("w:hanging")/$this->MTFactor)."px;";
							}
							if ($st1->getAttribute("w:firstLine") <>''){
								$Rstyle[$Fstyle]['Ifirst'] =  "text-indent: ".round($st1->getAttribute("w:firstLine")/$this->MTFactor)."px;";
							}
						}
						if($st1->name == "w:jc") { // Checks for paragragh alignment
							switch($st1->getAttribute("w:val")) {
								case "left":
									$Rstyle[$Fstyle]['Align'] =  "text-align: left;";
									break;
								case "center":
									$Rstyle[$Fstyle]['Align'] =  "text-align: center;";
									break;
								case "right":
									$Rstyle[$Fstyle]['Align'] =  "text-align: right;";
									break;
									case "both":
									$Rstyle[$Fstyle]['Align'] =  "text-align: justify;";
									break;
							}
						}
						if($st1->name == "w:shd" && $st1->getAttribute("w:fill") != "000000") { // get background colour
							$Rstyle[$Fstyle]['Bcolor'] = $st1->getAttribute("w:fill");
						}
						if ($st1->nodeType == XMLREADER::ELEMENT && $st1->name === 'w:tblBorders') { //Get table border style
							$tc2 = new SimpleXMLElement($st1->readOuterXML());
							foreach ($tc2->children('w',true) as $ch) {
								if (in_array($ch->getName(), ['top','left','bottom','right','insideH','insideV']) ) {
									$line = $this->convertLine($ch['val']);
									if ($ch['color'] == 'auto'){
										$tbc = '000000';
									} else {
										$tbc = $ch['color'];
									}
									$zlinB = $ch['sz']/4;
									if ($zlinB >0 AND $zlinB <1){
										$zlinB = 1;
									} else{
										$zlinB = round($zlinB);
									}
									$Bname = "B".$ch->getName();
									$Rstyle[$Fstyle][$Bname] = ":".$zlinB."px ".$line." #".$tbc.";";
								}
							}
						}
						if ($st1->nodeType == XMLREADER::ELEMENT && $st1->name === 'w:tblCellMar') { //Get table margin styles
							$tc3 = new SimpleXMLElement($st1->readOuterXML());
							foreach ($tc3->children('w',true) as $ch) {
								if (in_array($ch->getName(), ['top','left','bottom','right']) ) {
									$zlinM = round($ch['w']/$this->MTFactor);
									$Mname = "M".$ch->getName();
									$Rstyle[$Fstyle][$Mname] = ":".$zlinM."px;";
								}
							}
						}
					}
					if ($FontTheme AND $Rfont['major']){ // get font defined by Font Theme or its absence
						$Rstyle[$Fstyle]['ThFont'] = " font-family: ".$Rfont['major'].";";
					} else if (! $FontTheme AND $Rfont['minor']){
						$Rstyle[$Fstyle]['ThFont'] = " font-family: ".$Rfont['minor'].";";
					}
					$FontTheme = '';
			}
		}
		$Rstyle['Theme']['minor'] = " font-family: ".$Rfont['minor'].";";
		$this->Rstyle = $Rstyle; // all the style parameters can now be accessed anywhere in the class
	}
	
	

	/**
	 * CONVERTS A NUMBER TO ITS ROMAN PRESENTATION
	 * @param String/Integer $num - The number to be converted
	 * @return String - Roman number
	**/ 
	function numberToRoman($num)  
	{ 
		// Be sure to convert the given parameter into an integer
		$n = intval($num);
		$result = ''; 
 
		// Declare a lookup array that we will use to traverse the number: 
		$lookup = array(
			'm' => 1000, 'cm' => 900, 'd' => 500, 'cd' => 400, 
			'c' => 100, 'xc' => 90, 'l' => 50, 'xl' => 40, 
			'x' => 10, 'ix' => 9, 'v' => 5, 'iv' => 4, 'i' => 1
		); 
 
		foreach ($lookup as $roman => $value)  
		{
			// Look for number of matches
			$matches = intval($n / $value); 
 
			// Concatenate characters
			$result .= str_repeat($roman, $matches); 
 
			// Substract that from the number 
			$n = $n % $value; 
		} 

		return $result; 
	} 

	/**
	 * CHECKS THE FONT FORMATTING OF A GIVEN ELEMENT
	 * 
	 * @param XML $xml - The XML node
	 * @param String $Pstyle - The name of the paragraph style
	 * @param String $Dcap - The type of drop capital if it exists
	 * @return Array - The elements styling and text
	 */
	private function checkFormating(&$xml,$Pstyle,$Dcap)
	{	
		$node = trim($xml->readOuterXML());
		$t = '';
		// add <br> tags
		if (strstr($node,'<w:br ')) $t = '<br>';					 
		// look for formatting tags
		$f = "<span style='";
		if ($Dcap == 'drop' OR $Dcap == 'margin'){
			$f .= "float:left; line-height: 80%; ";
		}
		$reader = new XMLReader();
		$reader->XML($node);
		$ret= array();
		$img = null;
		$Footref = '';
		$Ttmp = $script = $tcol = $Sfont = $tch = $tch1 = $Tstr = $relId = $US = '';
		static $zimgcount = 1;
		$Lstyle = array();
		$Icrop = array();
		$TT = $ac = '';
		$ImgL = $Wtmp = $Htmp = $Ltmp = $Imgpos = '';

		$Wingding1 = array(32 => 32, 128393, 9986, 9985, 128083, 128365, 128366, 128367, 128383, 9990, 128386, 128387, 128234, 128235, 128236, 128237, 128193, 128194, 128196, 128463, 128464, 128452, 8987, 128430, 128432, 128434, 128435, 128436, 128427, 128428, 9991, 9997, 128398, 9996, 128076, 128077, 128078, 9756, 9758, 9757, 9759, 128400, 9786, 128528, 9785, 128163, 9760, 127987, 127985, 9992, 9788, 128167, 10052, 128326, 10014, 128328, 10016, 10017, 9770, 9775, 2384, 9784, 9800, 9801, 9802, 9803, 9804, 9805, 9806, 9807, 9808, 9809, 9810, 9811, 128624, 128629, 9679, 128318, 9632, 9633, 128912, 10065, 10066, 11047, 10731, 9670, 10070, 11045, 8999, 11193, 8984, 127989, 127990, 128630, 128631, 128=>9450, 9312, 9313, 9314, 9315, 9316, 9317, 9318, 9319, 9320, 9321, 9471, 10102, 10103, 10104, 10105, 10106, 10107, 10108, 10109, 10110, 10111, 128610, 128608, 128609, 128611, 128606, 128604, 128605, 128607, 183, 8226, 9642, 9898, 128902, 128904, 9673, 9678, 128319, 9642, 9723, 128962, 10022, 9733, 10038, 10036, 10041, 10037, 11216, 8982, 10209, 8977, 11217, 10026, 10032, 128336, 128337, 128338, 128339, 128340, 128341, 128342, 128343, 128344, 128345, 128346, 128347, 11184, 11185, 11186, 11187, 11188, 11189, 11190, 11191, 128618, 128619, 128597, 128596, 128599, 128598, 128592, 128593, 128594, 128595, 9003, 8998, 11160, 11162, 11161, 11163, 11144, 11146, 11145, 11147, 129128, 129130, 129129, 129131, 129132, 129133, 129135, 129134, 129144, 129146, 129145, 129147, 129148, 129149, 129151, 129150, 8678, 8680, 8679, 8681, 11012, 8691, 11008, 11009, 11011, 11010, 129196, 129197, 128502, 10004, 128503, 128505, 32);
		$Wingding2 = array(32 => 32, 128394, 128395, 128396, 128397, 9988, 9984, 128382, 128381, 128453, 128454, 128455, 128456, 128457, 128458, 128459, 128460, 128461, 128203, 128465, 128468, 128437, 128438, 128439, 128440, 128429, 128431, 128433, 128402, 128403, 128408, 128409, 128410, 128411, 128072, 128073, 128412, 128413, 128414, 128415, 128416, 128417, 128070, 128071, 128418, 128419, 128401, 128500, 10003, 128501, 9745, 9746, 9746, 11198, 11199, 10680, 10680, 128625, 128628, 128626, 128627, 8253, 128633, 128634, 128635, 128614, 128612, 128613, 128615, 128602, 128600, 128601, 128603, 9450, 9312, 9313, 9314, 9315, 9316, 9317, 9318, 9319, 9320, 9321, 9471, 10102, 10103, 10104, 10105, 10106, 10107, 10108, 10109, 10110, 10111, 128=>9737, 127765, 9789, 9790, 11839, 10013, 128327, 128348, 128349, 128350, 128351, 128352, 128353, 128354, 128355, 128356, 128357, 128358, 128359, 128616, 128617, 8226, 9679, 9899, 11044, 128901, 128902, 128903, 128904, 128906, 10687, 9726, 9632, 9724, 11035, 11036, 128913, 128914, 128915, 128916, 9635, 128917, 128918, 128919, 11049, 11045, 9670, 9671, 128922, 9672, 128923, 128924, 128925, 11050, 11047, 10731, 9674, 128928, 9686, 9687, 11210, 11211, 9724, 11045, 11039, 11202, 11043, 11042, 11203, 11204, 128929, 128930, 128931, 128932, 128933, 128934, 128935, 128936, 128937, 128938, 128939, 128940, 128941, 128942, 128943, 128944, 128945, 128946, 128947, 128948, 128949, 128950, 128951, 128952, 128953, 128954, 128955, 128956, 128957, 128958, 128959, 128960, 128962, 128964, 10022, 128969, 9733, 10038, 128971, 10039, 128975, 128978, 10041, 128963, 128967, 10031, 128973, 128980, 11212, 11213, 8251, 8258);
		$Wingding3 = array(32 => 32, 11104, 11106, 11105, 11107, 11110, 11111, 11113, 11112, 11120, 11122, 11121, 11123, 11126, 11128, 11131, 11133, 11108, 11109, 11114, 11116, 11115, 11117, 11085, 11168, 11169, 11170, 11171, 11172, 11173, 11174, 11175, 11152, 11153, 11154, 11155, 11136, 11139, 11134, 11135, 11140, 11142, 11141, 11143, 11151, 11149, 11150, 11148, 11118, 11119, 9099, 8996, 8963, 8997, 9141, 9085, 8682, 11192, 129184, 129185, 129186, 129187, 129188, 129189, 129190, 129191, 129192, 129193, 129194, 129195, 8592, 8594, 8593, 8595, 8598, 8599, 8601, 8600, 129112, 129113, 9650, 9660, 9651, 9661, 9668, 9658, 9665, 9655, 9699, 9698, 9700, 9701, 128896, 128898, 128897, 128=>128899, 9650, 9660, 9664, 9654, 11164, 11166, 11165, 11167, 129040, 129042, 129041, 129043, 129044, 129046, 129045, 129047, 129048, 129050, 129049, 129051, 129052, 129054, 129053, 129055, 129024, 129026, 129025, 129027, 129028, 129030, 129029, 129031, 129032, 129034, 129033, 129035, 129056, 129058, 129060, 129062, 129064, 129066, 129068, 129180, 129181, 129182, 129183, 129070, 129072, 129074, 129076, 129078, 129080, 129082, 129081, 129083, 129176, 129178, 129177, 129179, 129084, 129086, 129085, 129087, 129088, 129090, 129089, 129091, 129092, 129094, 129093, 129095, 11176, 11177, 11178, 11179, 11180, 11181, 11182, 11183, 129120, 129122, 129121, 129123, 129124, 129125, 129127, 129126, 129136, 129138, 129137, 129139, 129140, 129141, 129143, 129142, 129152, 129152, 129153, 129155, 129156, 129157, 129159, 129158, 129168, 129170, 129169, 129171, 129172, 129174, 129173, 129175);
		$Webdings = array(32=>32, 128375, 128376, 128370, 128374, 127942, 127894, 128391, 128488, 128489, 128496, 128497, 127798, 127895, 128638, 128636, 128469, 128470, 128471, 9204, 9205, 9206, 9207, 9194, 9193, 9198, 9197, 9208, 9209, 9210, 128474, 128499, 128736, 127959, 127960, 127961, 127962, 127964, 127981, 127963, 127968, 127958, 127965, 128739, 128269, 127956, 128065, 128066, 127966, 127957, 128740, 127967, 128755, 128364, 128363, 128360, 128264, 127892, 127893, 128492, 128637, 128493, 128490, 128491, 11156, 10004, 128690, 9633, 128737, 128230, 128753, 9632, 128657, 128712, 128745, 128752, 128968, 128372, 9899, 128741, 128660, 128472, 128473, 10067, 128754, 128647, 128653, 9971, 128711, 8854, 128685, 128494, 124, 128495, 128498, 128=>128697, 128698, 128713, 128714, 128700, 128125, 127947, 9975, 127938, 127948, 127946, 127940, 127949, 127950, 128664, 128480, 128738, 128176, 127991, 128179, 128106, 128481, 128482, 128483, 10031, 128388, 128389, 128387, 128390, 128441, 128442, 128443, 128373, 128368, 128445, 128446, 128203, 128466, 128467, 128214, 128218, 128478, 128479, 128451, 128450, 128444, 127917, 127900, 127896, 127897, 127911, 128191, 127902, 128247, 127903, 127916, 128253, 128249, 128254, 128251, 127898, 127899, 128250, 128187, 128421, 128422, 128423, 128377, 127918, 128379, 128380, 128223, 128385, 128384, 128424, 128425, 128447, 128426, 128476, 128274, 128275, 128477, 128229, 128594, 128371, 127779, 127780, 127781, 127782, 9729, 127783, 127784, 127785, 127786, 127788, 127787, 127772, 127777, 128715, 128719, 127869, 127864, 128718, 128717, 9413, 9855, 128710, 128392, 127891, 128484, 128485, 128486, 128487, 128746, 128063, 128038, 128031, 128021, 128008, 128620, 128622, 128621, 128623, 128506, 127757, 127759, 127758, 128330);
		$Symbol = array(32=>32, 33, 8704, 35, 8707, 37, 38, 8717, 40, 41, 8727, 43, 44, 8722, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 8773, 913, 914, 935, 916, 917, 934, 915, 919, 921, 977, 922, 923, 924, 925, 927, 928, 920, 929, 931, 932, 933, 962, 937, 926, 936, 918, 91, 8756, 93, 8869, 95, 32, 945, 946, 967, 948, 949, 981, 947, 951, 953, 966, 954, 955, 956, 957, 959, 960, 952, 961, 963, 964, 965, 982, 969, 958, 968, 950, 123, 124, 125, 8764, 161=>978, 8242, 8804, 8260, 8734, 402, 9827, 9830, 9829, 9824, 8596, 8592, 8593, 8594, 8595, 176, 177, 8243, 8805, 180, 8733, 8706, 8226, 184, 8800, 8801, 8776, 8230, 9168, 9135, 8629, 8501, 8465, 8476, 8472, 8855, 8853, 8709, 8745, 8746, 8835, 8839, 8836, 8834, 8838, 8712, 8713, 8736, 8711, 210, 211, 8482, 8719, 8730, 8901, 216, 8743, 8744, 8660, 8656, 8657, 8658, 8659, 9674, 9001, 226, 227, 8482, 8721, 9115, 9116, 9117, 9121, 9122, 9123, 9127, 9128, 9129, 9130, 8364, 9002, 8747, 8992, 9134, 8993, 9118, 9119, 9420, 9124, 9125, 9126, 9131, 9132, 9133);

		while ($reader->read()) {
			if ($reader->nodeType == XMLREADER::END_ELEMENT && $reader->name === 'w:drawing') {
				$dr = '';
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name === 'w:drawing'){
				$dr = 'Y';
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'mc:AlternateContent') {
				$ac = 'Y';
			}
			if ($reader->nodeType == XMLREADER::END_ELEMENT && $reader->name == 'mc:AlternateContent') {
				$ac = '';
			}
			if ($ac == ''){
				if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'w:instrText') {
					$Htext = htmlentities($reader->expand()->textContent);
					if (substr($Htext,0,5) == "HYPER"){
						$Htext = substr($Htext,16);
						$ret['Htext'] = substr($Htext,0,-6);
					}
					if (substr($Htext,0,4) == " REF"){
						$ret['CRtext'] = substr($Htext,6,11);
					}
				}
				if ($reader->name === 'w:rStyle' && $reader->getAttribute("w:val") == "Hyperlink") {
					$ret['Hyper'] = 'Y';
				}
				if ($reader->name === 'w:tab') {
					$Ttmp .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
				}
				if($reader->name == "w:br") { // Checks for page break
					if ($reader->getAttribute("w:type") == 'page'){
						$ret['Pbreak'] = 'Y';
					}
				}
				if($reader->name == "w:b") {
					$Lstyle['Bold'] = "font-weight: bold;";
				}
				if($reader->name == "w:u") {
					$TH = '';
					$Ustyle = $reader->getAttribute("w:val");
					$Ucol = "#".$reader->getAttribute("w:color");
					if ($Ustyle == 'single'){
						$US = 'solid';
					} else if ($Ustyle == 'double'){
						$US = 'double';
					} else if ($Ustyle == 'dotted'){
						$US = 'dotted';
					} else if ($Ustyle == 'dottedHeavy'){
						$US = 'dotted';
						$TH = ' text-decoration-thickness: 4px; ';
					} else if ($Ustyle == 'dash'){
						$US = 'dashed';
					} else if ($Ustyle == 'dashedHeavy'){
						$US = 'dashed';
						$TH = ' text-decoration-thickness: 4px; ';
					} else if ($Ustyle == 'dashLong'){
						$US = 'dashed';
					} else if ($Ustyle == 'dashLongHeavy'){
						$US = 'dashed';
						$TH = ' text-decoration-thickness: 4px; ';
					} else if ($Ustyle == 'dotDash'){
						$US = 'dashed';
					} else if ($Ustyle == 'dashDotHeavy'){
						$US = 'dashed';
						$TH = ' text-decoration-thickness: 4px; ';
					} else if ($Ustyle == 'dotDotDash'){
						$US = 'dotted';
					} else if ($Ustyle == 'dashDotDotHeavy'){
						$US = 'dotted';
						$TH = ' text-decoration-thickness: 4px; ';
					} else if ($Ustyle == 'wave'){
						$US = 'wavy';
					} else if ($Ustyle == 'wavyHeavy'){
						$US = 'wavy';
						$TH = ' text-decoration-thickness: 4px; ';
					} else if ($Ustyle == 'wavyDouble'){
						$US = 'wavy';
						$TH = ' text-decoration-thickness: 4px; ';
					} else if ($Ustyle == 'thick'){
						$TH = ' text-decoration-thickness: 4px; ';
					} else {
						$US = 'solid';
					}
				
					if ($Ucol == '#'){
						$Lstyle['Under'] = "text-decoration: underline; text-decoration-style: ".$US.";".$TH;
					} else {
						$Lstyle['Under'] = "text-decoration: underline; text-decoration-style: ".$US."; text-decoration-color: ".$Ucol.";".$TH;
					}
				}
				if($reader->name == "w:i") {
					$Lstyle['Italic'] = " font-style: italic;";
				}
				if($reader->name == "w:color") {
					$Lstyle['Color'] = $reader->getAttribute("w:val");
				}
				if($reader->name == "w:rFonts") {
					 $FF = $reader->getAttribute("w:ascii");
					 if ($FF == ''){
						 $FF = 'Times New Roman';
					 }
					 if (substr($FF,0,9) == 'Helvetica'){
						 $FF = 'Helvetica';
					 } else  if ($FF == 'Wingdings' or $FF == 'ZapfDingbats'){
						 $FF = 'Times New Roman';
						 $Sfont = 'Wing1';
					 } else  if ($FF == 'Wingdings 2'){
						 $FF = 'Times New Roman';
						 $Sfont = 'Wing2';
					 } else  if ($FF == 'Wingdings 3'){
						 $FF = 'Times New Roman';
						 $Sfont = 'Wing3';
					 } else  if ($FF == 'Webdings'){
						 $FF = 'Times New Roman';
						 $Sfont = 'Web';
					 } else  if ($FF == 'Symbol'){
						 $FF = 'Times New Roman';
						 $Sfont = 'Sym';
					 }
					 $Lstyle['Font'] = "font-family: ".$FF.";";
				}
				if($reader->name == "w:shd" && $reader->getAttribute("w:val") != "clear") {
					$Lstyle['background-color'] = $reader->getAttribute("w:fill");
				}
				if($reader->name == "w:strike") {
					$f .=" text-decoration:line-through;";
				}
				if($reader->name == "w:dstrike") {
					$f .=" text-decoration:line-through; text-decoration-style: double;";
				}
				if($reader->name == "w:vertAlign" && $reader->getAttribute("w:val") == "superscript") {
					$f .="position: relative; top: -0.6em;";
					$script = 'Y';
				}
				if($reader->name == "w:vertAlign" && $reader->getAttribute("w:val") == "subscript") {
					$f .="position: relative; bottom: -0.5em;";
					$script = 'Y';
				}
				if($reader->name == "w:sz") {
					$Lstyle['FontS'] = round($reader->getAttribute("w:val")/$this->FSFactor,2);
				}
				if($reader->name == "w:footnoteReference") {
					$Ftmp = $reader->getAttribute("w:id");
					$Footref = "<a id='FN".$Ftmp."R' href='#FN".$Ftmp."'>[".$Ftmp."]</a>";
					$f .="position: relative; top: -0.6em;font-weight: bold;";
					$script = 'Y';
				}
				if($reader->name == "w:endnoteReference") {
					$Ftmp = $reader->getAttribute("w:id");
					$Footref = "<a id='EN".$Ftmp."R' href='#EN".$Ftmp."'>[".$this->numberToRoman($Ftmp)."]</a>";
					$f .="position: relative; top: -0.6em;font-weight: bold;";
					$script = 'Y';
				}
				
				if ($this->Icss <> 'O'){
					if($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'w:drawing' ) { // Get a lower resolution image
						$r = $this->checkImageFormating($reader);
						if ($this->Icss == 'Y'){
							$img = $r !== null ? "<image class='Wimg".$zimgcount."' src='".$r['image']."' />" : null;
						} else {
							$img = $r !== null ? "<image src='".$r['image']."' ".$r['style']." />" : null;
						}
					}
					if($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'v:shape' ) { // get size of higher resolution image
						$Psize = $reader->getAttribute("style");
						$arr = explode(';', $Psize); // Get image size if $Psize was supplied to this function
						$l = count($arr);
						$d = 0;
						while ($d < $l){
							if (substr($arr[$d],0,5) == 'width' ) {
								$Wtmp = substr($arr[$d],6);
								$ImgW = (float)substr($Wtmp,0,-2) * 1.4;
							}
							if (substr($arr[$d],0,6) == 'height' ) {
								$Htmp = substr($arr[$d],7);
								$ImgH = (float)substr($Htmp,0,-2) * 1.4;
							}
							if (substr($arr[$d],0,11) == 'margin-left' ) {
								$Ltmp = substr($arr[$d],12);
								$ImgL = substr($Ltmp,0,-2);
							}
							$d++;
						}
					}
					if($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'v:imagedata') { // For high resolution images get image and cropping details if they exist
						$relId = $reader->getAttribute("r:id");
						$notfound = false;
						if ($reader->getAttribute("croptop")){
							$Ctop = substr($reader->getAttribute("croptop"),0,-1);
							$TT = 'Y';
						}
						if ($reader->getAttribute("cropbottom")){
							$Cbot = substr($reader->getAttribute("cropbottom"),0,-1);
							$Cbot = 64000 - $Cbot;
							$TT = 'Y';
						}
						if ($reader->getAttribute("cropleft")){
							$Cleft = substr($reader->getAttribute("cropleft"),0,-1);
							$TT = 'Y';
						}
						if ($reader->getAttribute("cropright")){
							$Cright = substr($reader->getAttribute("cropright"),0,-1);
							$Cright = 64000 - $Cright;
							$TT = 'Y';
						}
						if ($TT == 'Y'){
							$Cwidth = $Cright - $Cleft;
							$CwidthPC = $Cwidth / 64000;
							$CleftPC = $Cleft /64000;
							$Cheight = $Cbot - $Ctop;
							$CheightPC = $Cheight / 64000;
							$CtopPC = $Ctop /64000;
							$Icrop['left'] = $CleftPC;
							$Icrop['top'] = $CtopPC;
							$Icrop['width'] = $CwidthPC;
							$Icrop['height'] = $CheightPC;
						}
						if ($ImgL <> ''){
							$Imgpos = ($ImgL < 50) ? "float:left;" : "float:right;";
						}
						$r['style'] = "style='".$Imgpos."width:".$ImgW."px; height:".$ImgH."px; padding:10px 15px 10px 15px;'";
						// image id found, get the image location
						if (!$notfound && $relId) {
							$reader = new XMLReader();
							if ($this->HF == ''){
								$reader->XML($this->rels_xml->saveXML());
							} else if ($this->HF == 'H1'){
								$reader->XML($this->head1_rels_xml->saveXML());
							} else if ($this->HF == 'H2'){
								$reader->XML($this->head2_rels_xml->saveXML());
							} else if ($this->HF == 'H3'){
								$reader->XML($this->head3_rels_xml->saveXML());
							} else if ($this->HF == 'F1'){
								$reader->XML($this->foot1_rels_xml->saveXML());
							} else if ($this->HF == 'F2'){
								$reader->XML($this->foot2_rels_xml->saveXML());
							} else if ($this->HF == 'F3'){
								$reader->XML($this->foot3_rels_xml->saveXML());
							}
							while ($reader->read()) {
								if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name=='Relationship') {
									if($reader->getAttribute("Id") == $relId) {
										$link = "word/".$reader->getAttribute('Target');
										break;
									}
								}
							}
						}
					
						$zip = new ZipArchive();
						$im = null;
						if (true === $zip->open($this->file)) {
							$r['image'] = $this->createImage($zip->getFromName($link), $relId, $link, $Icrop);
						}
						$zip->close();
						if ($this->Icss == 'Y'){
							$img = $r !== null ? "<image class='Wimg".$zimgcount."' src='".$r['image']."' />" : null;
						} else {
							$img = $r !== null ? "<image src='".$r['image']."' ".$r['style']." />" : null;
						}
					}
				}
				if ($reader->name == "w:t") { // Find text and also substitute any symbols found with their Unicode alternatives
					$Tmptext1 = htmlentities($reader->expand()->textContent);
					$Tmptext2 = preg_replace('~(?<=\s)\s~', '&nbsp;', $Tmptext1);
					if ($Sfont <> '' AND $Tmptext1 <> ''){
						if (substr (PHP_VERSION,0,3) >= '7.2'){
							$tch = mb_ord($Tmptext1)-61440;
							if ($Sfont == 'Wing1'){
								$tch1 = $Wingding1[$tch];
							} else if ($Sfont == 'Wing2'){
								$tch1 = $Wingding2[$tch];
							} else if ($Sfont == 'Wing3'){
								$tch1 = $Wingding3[$tch];
							} else if ($Sfont == 'Web'){
								$tch1 = $Webdings[$tch];
							} else if ($Sfont == 'Sym'){
								$tch1 = $Symbol[$tch];
							}
							$Tstr .= mb_chr($tch1);	
							$Ttmp .= $Tstr;	
						} else {
							$Ttmp .= "&nbsp;";
						}
					} else {
						$Ttmp .= $Tmptext2;
					}
				}
				if($reader->name == "w:sym") {
					if (substr (PHP_VERSION,0,3) >= '7.2'){

						$SF = $reader->getAttribute("w:font");
						$SC = $reader->getAttribute("w:char");
						$SCD = hexdec(substr($SC,-2,2));
						if ($SF == 'Wingdings' or $SF == 'ZapfDingbats'){
							$RC = $Wingding1[$SCD];
							$Ttmp .= mb_chr($RC);
						} else if ($SF == 'Wingdings 2'){
							$RC = $Wingding2[$SCD];
							$Ttmp .= mb_chr($RC);
						} else if ($SF == 'Wingdings 3'){
							$RC = $Wingding3[$SCD];
							$Ttmp .= mb_chr($RC);
						} else if ($SF == 'Webdings'){
							$RC = $Webdings[$SCD];
							$Ttmp .= mb_chr($RC);
						} else if ($SF == 'Symbol'){
							$RC = $Symbol[$SCD];
							$Ttmp .= mb_chr($RC);
						}
					}
				}
			}
		}
		if ($img !== null){
			$zimgcount++;
		}

		if (isset($Lstyle['Bold'])){
			$f .= $Lstyle['Bold'];
		} else if(isset($this->Rstyle[$Pstyle]['Bold'])){
			$f .= $this->Rstyle[$Pstyle]['Bold'];
		} else if(isset($this->Rstyle['Normal']['Bold'])){
			$f .= $this->Rstyle['Normal']['Bold'];
		}
		if (isset($Lstyle['Under'])){
			$f .= $Lstyle['Under'];
		} else if(isset($this->Rstyle[$Pstyle]['Under'])){
			$f .= $this->Rstyle[$Pstyle]['Under'];
		} else if(isset($this->Rstyle['Normal']['Under'])){
			$f .= $this->Rstyle['Normal']['Under'];
		}
		if (isset($Lstyle['Italic'])){
			$f .= $Lstyle['Italic'];
		} else if(isset($this->Rstyle[$Pstyle]['Italic'])){
			$f .= $this->Rstyle[$Pstyle]['Italic'];
		} else if(isset($this->Rstyle['Normal']['Italic'])){
			$f .= $this->Rstyle['Normal']['Italic'];
		}
		if (isset($Lstyle['Font'])){
			$f .= $Lstyle['Font'];
		} else if (isset($this->Rstyle[$Pstyle]['Font'])){
			$f .= $this->Rstyle[$Pstyle]['Font'];
		} else if (isset($this->Rstyle['Normal']['Font'])){
			$f .= $this->Rstyle['Normal']['Font'];
		} else if (isset($this->Rstyle[$Pstyle]['ThFont'])){
			$f .= $this->Rstyle[$Pstyle]['ThFont'];
		} else if (isset($this->Rstyle['Theme']['minor'])){
			$f .= $this->Rstyle['Theme']['minor'];
		} else if (isset($this->Rstyle['Default']['Font'])){
			$f .= $this->Rstyle['Default']['Font'];
		}else{
		}
		if (isset($Lstyle['FontS'])){
			$Fsize = $Lstyle['FontS'];
		} else if (isset($this->Rstyle[$Pstyle]['FontS'])){
			$Fsize = $this->Rstyle[$Pstyle]['FontS'];
		} else if (isset($this->Rstyle['Normal']['FontS'])){
			$Fsize = $this->Rstyle['Normal']['FontS'];
		} else if (isset($this->Rstyle['Default']['FontS'])){
			$Fsize = $this->Rstyle['Default']['FontS'];
		}
		if ($script == 'Y'){
			$f .= " font-size: ".$Fsize * 0.65 ."rem;";
		} else {
			$f .= " font-size: ".$Fsize."rem;";
		}
		if (isset($Lstyle['Bcolor'])){
			$Bcolor = $Lstyle['Bcolor'];
			$f .= " background-color: #".$Lstyle['Bcolor'].";";
		} else if(isset($this->Rstyle[$Pstyle]['Bcolor'])){
			$Bcolor = $this->Rstyle[$Pstyle]['Bcolor'];
			$f .= " background-color: #".$this->Rstyle[$Pstyle]['Bcolor'].";";
		}
		if (isset($Lstyle['Color'])){
			$tcol = $Lstyle['Color'];
		} else if(isset($this->Rstyle[$Pstyle]['Color'])){
			$tcol = $this->Rstyle[$Pstyle]['Color'];
		} else if(isset($this->Rstyle['Normal']['Color'])){
			$tcol = $this->Rstyle['Normal']['Color'];
		} else if(isset($this->Rstyle['Default']['Color'])){
			$tcol = $this->Rstyle['Default']['Color'];
		}
		if ($tcol == 'auto'){
			if (isset($Bcolor)){
				$red = hexdec(substr($Bcolor,0,2));
				$green = hexdec(substr($Bcolor,0,2));
				$blue = hexdec(substr($Bcolor,0,2));
				$color = (($red * 0.299) + ($green * 0.587) + ($blue * 0.114) > 186) ?  '000000' : 'FFFFFF';
				$f .= "color: #".$color.";";
			} else {
				$f .= "color: #000000;";
			}
		} else if ($tcol <> ''){
			$f .= "color: #".$tcol.";";
		}
		if (isset($this->Rstyle[$Pstyle]['Caps'])){
			$f .= $this->Rstyle[$Pstyle]['Caps'];
		}
		
		$f = rtrim($f, ',');
		$f .= "'>";
		if ($Footref <> ''){
			$t = $Footref;
		} else {
			$t .= ($img !== null ? $img : $Ttmp);
		}
		$ret['style'] = $f;
		$ret['text'] = $t;
		return $ret;
	}
	

	
	/**
	 * CHECKS THE ELEMENT FOR List ELEMENTS and their numbering and also PARAGRAGH FORMATTING
	 * 
	 * @param XML $xml - The XML node
	 * @param String $Tstyle - The name of the table style
	 * @return Array - Paragraph Styling and list details
	 */
	private function getListFormating(&$xml,$Tstyle)
	{
		
		$PSret= array();
		$DC = $Pstyle = $amar = $bmar = $cmar = $dmar = $PSret['Dcap'] = $aind = $bind = $cind = $dind = $Listlevel = $bcol = $hr = $Lnum = $palign = $Slsz = $Slnum = '';
		$node = trim($xml->readOuterXML());
		if ($node <>''){
			$reader = new XMLReader();
			$reader->XML($node);
			$LnumA = array();
			$ListnumId = '';
			static $Listcount = array(array());
			static $numb_xml = null;
		
			while ($reader->read()){
				if($reader->name == "w:framePr") { // get font style for list numbering
					$PSret['Dcap'] = $reader->getAttribute("w:dropCap");
				}
				if($reader->name == "w:pStyle" && $reader->hasAttributes ) {
					$Pstyle = $reader->getAttribute("w:val");
					$PSret['style'] = $Pstyle; //return the element's style
					if (substr($Pstyle,0,7) == 'numpara'){
						$ListnumId = $this->Rstyle[$Pstyle]['parnum'];
						$Listlevel = 0;
					}
				}
				if($reader->name == "w:ilvl" && $reader->hasAttributes) { // List formating - list level
					$Listlevel = $reader->getAttribute("w:val");
				}
				if($reader->name == "w:numId" && $reader->hasAttributes) { // List formating - List cross reference
					$ListnumId = $reader->getAttribute("w:val");
				}

				if($reader->name == "w:spacing") { // Checks for paragraph spacing
					if ($reader->getAttribute("w:before") <>''){
						$bmar =  "margin-top: ".round($reader->getAttribute("w:before")/$this->MTFactor)."px;";
					}
					if ($reader->getAttribute("w:after") <>''){
						$amar =  "margin-bottom: ".round($reader->getAttribute("w:after")/$this->MTFactor)."px;";
					}

				}
				if($reader->name == "w:ind") { // Checks for paragraph indent
					if ($reader->getAttribute("w:left") <>''){
						$aind =  "padding-left: ".round($reader->getAttribute("w:left")/$this->MTFactor)."px;";
					}
					if ($reader->getAttribute("w:right") <>''){
						$bind =  "padding-right: ".round($reader->getAttribute("w:right")/$this->MTFactor)."px;";
					}
					if ($reader->getAttribute("w:hanging") <>''){
						$Thang = round($reader->getAttribute("w:hanging")/$this->MTFactor);
							if ($PSret['Dcap'] == 'margin'){
								$Thang = $Thang + 10;
							}
							$cind =  "text-indent: -".$Thang."px;";
					}
					if ($reader->getAttribute("w:firstLine") <>''){
						$dind =  "text-indent: ".round($reader->getAttribute("w:firstLine")/$this->MTFactor)."px;";
					}
				}
				if($reader->name == "w:jc") { // Checks for paragraph alignment
					switch($reader->getAttribute("w:val")) {
						case "left":
							$palign =  "text-align: left;";
							break;
						case "center":
							$palign =  "text-align: center;";
							break;
						case "right":
							$palign =  "text-align: right;";
							break;
						case "both":
							$palign =  "text-align: justify;";
							break;
					}
				} else if (isset($this->Rstyle[$Pstyle]['Align'])){
					$palign =  $this->Rstyle[$Pstyle]['Align'];
				}
				if($reader->name == "w:pBdr") { // Add horizontal line
					$hr2 = new XMLReader;
					$hr2->xml(trim($reader->readOuterXML()));
					while ($hr2->read()) {
						if($hr2->name == 'w:bottom') {
							$Hnum = $hr2->getAttribute("w:val");
							if ($Hnum == 'single' OR $Hnum == 'thinThickThinMediumGap'){
								$Slnum = "solid;";
							} else if ($Hnum == 'double' OR $Hnum == 'wave'){
								$Slnum = "solid; border-bottom:1px solid; height:3px;";
							} else if ($Hnum == 'dotted'){
								$Slnum = "dotted;";
							} else if ($Hnum == 'dashed'){
								$Slnum = "dashed;";
							}
							$Lsz = $hr2->getAttribute("w:sz");
							if ($Lsz == 6){
								$Slsz = "border-top: 1px ";
							} else if ($Lsz == 12){
								$Slsz = "border-top: 2px ";
							} else if ($Lsz == 18){
								$Slsz = "border-top: 3px ";
							} else if ($Lsz == 24){
								$Slsz = "border-top: 4px ";
							}
							$hr = "<hr width=100% style='border-width:0; ".$Slsz.$Slnum."'>";
						}
					}
				}
			}
		
			if ($ListnumId){
				if (!$numb_xml){
					$zip = new ZipArchive();
					$_xml_numb = 'word/numbering.xml';
		
					if (true === $zip->open($this->file)) {
						//Get the list references from the word numbering file
						if (($index = $zip->locateName($_xml_numb)) !== false) {
							$xml_numb = $zip->getFromIndex($index);
						}
						$zip->close();
					}

					$enc = mb_detect_encoding($xml_numb);
					$this->setXmlParts($numb_xml, $xml_numb, $enc);
		
					if($this->debug) {
						echo "<br>XML File : word/numbering.xml<br>";
						echo "<textarea style='width:100%; height: 200px;'>";
						echo $numb_xml->saveXML();
						echo "</textarea>";
					}
				}
			
			
			
				// look for the List reference number of this element
				$reader1 = new XMLReader();
				$reader1->XML($numb_xml->saveXML());
				while ($reader1->read()) {
					if ($reader1->nodeType == XMLREADER::ELEMENT && $reader1->name == 'w:num' && $reader1->getAttribute("w:numId") == $ListnumId) {
						$st1 = new XMLReader;
						$st1->xml(trim($reader1->readOuterXML()));
						while ($st1->read()) {
							if($st1->name == 'w:abstractNumId') {
								$ListAbsNo = $st1->getAttribute("w:val");
							}
						}
					}
				}
				// look for the List details of this element
				$reader2 = new XMLReader();
				$reader2->XML($numb_xml->saveXML());
				while ($reader2->read()) {
					if ($reader2->nodeType == XMLREADER::ELEMENT && $reader2->name == 'w:abstractNum' && $reader2->getAttribute("w:abstractNumId") == $ListAbsNo) {
						$st2 = new XMLReader;
						$st2->xml(trim($reader2->readOuterXML()));
						while ($st2->read()) {
							if($st2->nodeType == XMLREADER::ELEMENT && $st2->name == 'w:lvl') {
								$Rlvl = $st2->getAttribute("w:ilvl");
							}
							if($st2->name == 'w:start') {
							$Rstart[$Rlvl] = $st2->getAttribute("w:val");
							}
							if($st2->name == 'w:numFmt') {
								$Rnumfmt[$Rlvl] = $st2->getAttribute("w:val");
							}
							if($st2->name == 'w:lvlText') {
								$Rlvltxt[$Rlvl] = $st2->getAttribute("w:val");
							}
							if($st2->name == "w:ind") { // Gets the list hanging and indent
								if ($st2->getAttribute("w:left") <>''){
									$Rind[$Rlvl] =  "padding-left: ".round($st2->getAttribute("w:left")/$this->MTFactor)."px;";
								}
								if ($st2->getAttribute("w:hanging") <>''){
									$Rhang[$Rlvl] =  "text-indent: -".round($st2->getAttribute("w:hanging")/$this->MTFactor)."px;";
								}
							}
						}
					}
				}
			}
		
			$alphabet = array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y'. 'z');
		
  
			if ($ListnumId){ // If the element is a list element get its number
				$LNfirst = $LNlast = $ListSep = '';
				
				$Rlvlen = strlen($Rlvltxt[$Listlevel]);
				if (substr($Rlvltxt[$Listlevel],0,1) == '%' OR substr($Rlvltxt[$Listlevel],1,1) == '%'){ // Number based list
					if (substr($Rlvltxt[$Listlevel],1,1) == '%'){ //Find if there is an initial character and if so get it
						$LNfirst = substr($Rlvltxt[$Listlevel],0,1);
						$ListNPos = 2;
					} else {
						$ListNPos = 1;
					}
					$ln = 0;
					while ($ListNPos < $Rlvlen){
						$ListNum[$ln] = substr($Rlvltxt[$Listlevel],$ListNPos,1) - 1;
						if (($ListNPos + 2) < $Rlvlen) {
							$ListSep = substr($Rlvltxt[$Listlevel],$ListNPos+1,1);
							++$ln;
						} else if (($ListNPos + 1) < $Rlvlen) {
							$LNlast = substr($Rlvltxt[$Listlevel],$ListNPos+1,1);
						}
						$ListNPos = $ListNPos + 3;
					}
				} else if (is_numeric(substr($Rlvltxt[$Listlevel],0,1))){
					$nc = 0;
					$np = 0;
					while ($np == 0){
						if (substr($Rlvltxt[$Listlevel],$nc,1) == '%'){
							$np = $nc-1;
						}
						++$nc;
					}
					$LNfirst = substr($Rlvltxt[$Listlevel],0,$np+1);
					$ListNum[0] = substr($Rlvltxt[$Listlevel],$np+2,1) - 1;
					if (($np+3) <= $Rlvlen){
						$LNlast = substr($Rlvltxt[$Listlevel],$np+3,1);
					}
				} else {
					$ListNum[0] = 0;
				}
				if (!isset($Listcount[$ListnumId][$Listlevel])){
					$Listcount[$ListnumId][$Listlevel] = 0;
				}
				if ($Listcount[$ListnumId][$Listlevel] == 0){
					// Get the list number of the list element
					if (!isset($Rstart[$Listlevel])){ //Added
						$Rstart[$Listlevel] = 0;
					}
					$Listcount[$ListnumId][$Listlevel] = $Rstart[$Listlevel];
					$Listcount[$ListnumId][$Listlevel + 1] = 0;
				} else {
					$Listcount[$ListnumId][$Listlevel] = $Listcount[$ListnumId][$Listlevel] + 1;
					$Listcount[$ListnumId][$Listlevel + 1] = 0;
				}
				if ($Rnumfmt[$Listlevel] == 'bullet'){
					$Lcount = $Listlevel;
				} else {
					$Lcount = $ListNum[0];
				}
				$Lnum = $LNfirst;
				while ($Lcount <= $Listlevel){ // produce the list element number
					if (!isset($Listcount[$ListnumId][$Lcount])){
						$LnumA[$Lcount] = $Rstart[$Lcount];
					} else {
						$LnumA[$Lcount] = $Listcount[$ListnumId][$Lcount]; // The number of the list element
					}
					if ($Rnumfmt[$Lcount] == 'lowerLetter'){
						$LnumA[$Lcount] = $alphabet[$LnumA[$Lcount]-1];
					} else if ($Rnumfmt[$Lcount] == 'upperLetter'){
						$LnumA[$Lcount] = strtoupper($alphabet[$LnumA[$Lcount]-1]);
					} else if ($Rnumfmt[$Lcount] == 'lowerRoman'){
						$LnumA[$Lcount] = $this->numberToRoman($LnumA[$Lcount]);
					} else if ($Rnumfmt[$Lcount] == 'upperRoman'){
						$LnumA[$Lcount] = strtoupper($this->numberToRoman($LnumA[$Lcount]));
					} else if ($Rnumfmt[$Lcount] == 'bullet'){
						if (substr (PHP_VERSION,0,3) >= '7.2'){
							if (mb_ord($Rlvltxt[$Lcount])-61440 == 252){
								$LnumA[$Lcount] = mb_chr(10004);
							} else if (mb_ord($Rlvltxt[$Lcount])-61440 == 183){
								$LnumA[$Lcount] = mb_chr(8226);
							} else if (mb_ord($Rlvltxt[$Lcount])-61440 == 167){
								$LnumA[$Lcount] = mb_chr(9642);
							} else if (mb_ord($Rlvltxt[$Lcount])-61440 == 216){
								$LnumA[$Lcount] = mb_chr(11162);
							} else if (mb_ord($Rlvltxt[$Lcount])-61440 == 118){
								$LnumA[$Lcount] = mb_chr(10070);
							} else if (mb_ord($Rlvltxt[$Lcount]) == 111){
								$LnumA[$Lcount] = $Rlvltxt[$Lcount];
							} else if (mb_ord($Rlvltxt[$Lcount]) == 45){
								$LnumA[$Lcount] = $Rlvltxt[$Lcount];
							} else {
								$LnumA[$Lcount] = "◾";
							}
						} else {
							$LnumA[$Lcount] = "◾";
						}
					}
					if ($Rlvlen <> 0){
						$Lnum .= $LnumA[$Lcount];
						if ($Lcount < $Listlevel){
							$Lnum .= $ListSep;
						}
					}
					$Lcount++;
				}
				if ($Rlvlen <> 0){
					$Lnum = $Lnum.$LNlast."&nbsp;&nbsp;&nbsp;";
				}
			}
			$PSret['Lnum'] = $Lnum;  // return the element's list number
			$PSret['listnum'] = $ListnumId;
		}
			if ($bmar == ''){ //set margin-top
				if (isset($this->Rstyle['Row']['MRtop'])){
					$bmar =  " margin-top".$this->Rstyle['Row']['MRtop'];
				} else if (isset($this->Rstyle[$Pstyle]['MPtop'])){
					$bmar =  $this->Rstyle[$Pstyle]['MPtop'];
				} else if (isset($this->Rstyle[$Tstyle]['MPtop'])){
					$bmar =  $this->Rstyle[$Tstyle]['MPtop'];
				} else if (isset($this->Rstyle['TableNormal']['MPtop'])){ 
					$bmar =  ";".$this->Rstyle['TableNormal']['MPtop'];
				} else if (isset($this->Rstyle['Default']['Mtop'])){
					$bmar =  $this->Rstyle['Default']['Mtop'];
				} else {
					$bmar =  " margin-top:0px;";
				}	
			}
			if ($amar == ''){ // set margin-bottom
				if (isset($this->Rstyle['Row']['MRbot'])){
					$amar =  " margin-bottom".$this->Rstyle['Row']['MRbot'];
				} elseif (isset($this->Rstyle[$Pstyle]['MPbot'])){
					$amar =  $this->Rstyle[$Pstyle]['MPbot'];
				} else if (isset($this->Rstyle[$Tstyle]['MPbot'])){
					$amar =  $this->Rstyle[$Tstyle]['MPbot'];
				} else if (isset($this->Rstyle['TableNormal']['MPbot'])){
					$amar =  $this->Rstyle['TableNormal']['MPbot'];
				} else if (isset($this->Rstyle['Default']['Mbot'])){
					$amar =  $this->Rstyle['Default']['Mbot'];
				} else {
					$amar =  " margin-bottom:0px;";
				}
			}
			if ($PSret['Dcap'] == 'margin'){
				$cmar =  " margin-left:0px";
			} else if (isset($this->Rstyle[$Tstyle]['Mleft'])){ // set margin-left
				$cmar =  " margin-left".$this->Rstyle[$Tstyle]['Mleft'];
			} else if (isset($this->Rstyle['TableNormal']['Mleft'])){
				$cmar =  " margin-left".$this->Rstyle['TableNormal']['Mleft'];
			}
			if (isset($this->Rstyle[$Tstyle]['Mright'])){ //set margin-right
				$dmar =  " margin-right".$this->Rstyle[$Tstyle]['Mright'];
			} else if (isset($this->Rstyle['TableNormal']['Mright'])){
				$dmar =  " margin-right".$this->Rstyle['TableNormal']['Mright'];
			}
			if (isset($this->Rstyle[$Pstyle]['Bcolor'])){ // set text colour
				$bcol = " background-color:#".$this->Rstyle[$Pstyle]['Bcolor'].";";
			}
			if ($aind == ''){ // set left indent
				if (isset($Rind[$Listlevel])){
					$aind = $Rind[$Listlevel];
				} else if (isset($this->Rstyle[$Pstyle]['Ileft'])){
					$aind =  $this->Rstyle[$Pstyle]['Ileft'];
				}
			}
			if ($bind == ''){ // set right indent
				if (isset($this->Rstyle[$Pstyle]['Iright'])){
					$bind =  $this->Rstyle[$Pstyle]['Iright'];
				}
			}
			if ($cind == ''){ // set first line hanging indent
				if (isset($Rhang[$Listlevel])){
					$cind = $Rhang[$Listlevel];
				} else if (isset($this->Rstyle[$Pstyle]['Ihang'])){
					$cind =  $this->Rstyle[$Pstyle]['Ihang'];
				}
			}
			if ($dind == ''){ // set first line indent
				if (isset($this->Rstyle[$Pstyle]['Ifirst'])){
					$dind =  $this->Rstyle[$Pstyle]['Ifirst'];
				}
			}
			// return the paragraph styling
			$PSret['Pform'] = " style='".$bmar.$amar.$cmar.$dmar.$aind.$bind.$cind.$dind.$palign.$bcol."'";
			// return horizontal line
			$PSret['hr'] = $hr;
			return $PSret;
		$PSret['Dcap'] = '';
		$PSret['Style'] = '';
		$PSret['Pform'] = '';
		$PSret['Lnum'] = '';
		$PSret['listnum'] = '';
	}
	



	/**
	 * CHECKS IF THERE IS AN IMAGE PRESENT (Used for lower resolution images)
	 * 
	 * @param XML $xml - The XML node
	 * @return array The details of the image
	 */
	private function checkImageFormating(&$xml)
	{
		$content = trim($xml->readInnerXml());
		if (!empty($content)) {

			$relId;
			$notfound = true;
			$reader = new XMLReader();
			$reader->XML($content);
			static $Icount = 1;
			$Inline = $offset = $Imgpos = $Crop = $TT = $RT = $Irot = $IflipH = $IflipV = $trans = '';
			$Pcount = $Cleft = $CleftPC = $Ctop = $CtopPC = 0;
			$Cright = $Cbot = 100000;
			$Icrop = array();
			
			while ($reader->read()) {
				if ($reader->name == "wp:inline") { // Checks if image is 'Inline'
					$Inline = 'Y';
				}
				if ($reader->name == "wp:posOffset" AND $offset == '') {
					$offset = (int)$xml->expand()->textContent;  // Checks if text flows round image and if so which side of the page to float the image
				}
				if ($reader->name == "wp:extent") { // Get image size
					$ImgW = round($reader->getAttribute("cx")/9000);
					$ImgH = round($reader->getAttribute("cy")/9000);
				}
				if ($reader->name == "a:blip") { // Get image name
					if ($Pcount == 0){
						$relId = $reader->getAttribute("r:embed");
						$Pcount = 1;
						$notfound = false;
					} else {
						$relId = $reader->getAttribute("r:embed");  //In order to get the alternative .png image when the original is a .pdf image
						$notfound = false;
					}
				}
				if ($reader->name == "a:srcRect") { // Check if image is cropped and get its positions
					if ($reader->getAttribute("l")){
						$Cleft = $reader->getAttribute("l");
						$TT = 'Y';
					}
					if ($reader->getAttribute("t")){
						$Ctop = $reader->getAttribute("t");
						$TT = 'Y';
					}
					if ($reader->getAttribute("r")){
						$Cright = $reader->getAttribute("r");
						$Cright = 100000 - $Cright;
						$TT = 'Y';
					}
					if ($reader->getAttribute("b")){
						$Cbot = 100000 - $reader->getAttribute("b");
						$TT = 'Y';
					}
					if ($TT == 'Y'){
						$Cwidth = $Cright - $Cleft;
						$CwidthPC = $Cwidth / 100000;
						$CleftPC = $Cleft /100000;
						$Cheight = $Cbot - $Ctop;
						$CheightPC = $Cheight / 100000;
						$CtopPC = $Ctop /100000;
						$Icrop['left'] = $CleftPC;
						$Icrop['top'] = $CtopPC;
						$Icrop['width'] = $CwidthPC;
						$Icrop['height'] = $CheightPC;
					}
				}
				if ($reader->name == "a:xfrm") { // Check if image is rotated and/or flipped
					if ($reader->getAttribute("rot")){
						$Irot = $reader->getAttribute("rot")/60000;
						$RT = 'Y';
					}
					if ($reader->getAttribute("flipH")){
						$IflipH = $reader->getAttribute("flipH");
						$RT = 'Y';
					}
					if ($reader->getAttribute("flipV")){
						$IflipV = $reader->getAttribute("flipV");
						$RT = 'Y';
					}
				}
			}
			if ($RT == 'Y'){
				$trans = "transform:";
				if ($Irot <> ''){
					$trans .= " rotate(".$Irot."deg)";
				}
				if ($IflipH <> ''){
					$trans .= " scaleX(-1)";
				}
				if ($IflipV <> ''){
					$trans .= " scaleY(-1)";
				}
				$trans .= "; ";
				// find amount of padding needed for rotated image
				if ($Irot <> ''){
					if($Irot == '90' OR $Irot == '270'){
						if($ImgW > $ImgH){
							$diff = ceil((($ImgW - $ImgH)/2) + 10);
							$padd = "padding:".$diff."px 5px ".$diff."px 5px; ";				
						} else if($ImgH > $ImgW){
							$diff = ceil((($ImgW - $ImgH)/2) + 5);
							$padd = "padding:10px ".$diff."px 10px ".$diff."px; ";				
						} else {
							$padd = "padding:10px 5px 10px 5px; ";
						}
					} else if($Irot == 180){
						$padd = "padding:10px 5px 10px 5px; ";
					} else {
						if (($Irot > 0 AND $Irot <90) OR ($Irot > 180 AND $Irot <270)){
							if ($Irot > 180){
								$Irot = $Irot - 180;
							}
							$Rcot = deg2rad($Irot);
							$Rwidth = $ImgW * cos($Rcot) + $ImgH * sin($Rcot);
							$Rheight = $ImgW * sin($Rcot) + $ImgH * cos($Rcot);
						} else {
							if ($Irot > 270){
								$Irot = $Irot - 270;
							} else {
								$Irot = $Irot - 90;
							}
							$Rcot = deg2rad($Irot);
							$Rwidth = $ImgH * cos($Rcot) + $ImgW * sin($Rcot);
							$Rheight = $ImgH * sin($Rcot) + $ImgW * cos($Rcot);
						}
						if ($Rwidth > $ImgW){
							$Pwidth = ceil((($Rwidth - $ImgW) / 2) + 5);
						} else {
							$Pwidth = 5;
						}
						if ($Rheight > $ImgH){
							$Pheight = ceil((($Rheight - $ImgH) / 2) + 10);
						} else {
							$Pwidth = 10;
						}
						$padd = "padding:".$Pheight."px ".$Pwidth."px ".$Pheight."px ".$Pwidth."px; ";
					}
				} else {
					$padd = "padding:10px 5px 10px 5px; ";
				}
			} else {
				$padd = "padding:10px 5px 10px 5px; ";
			}
			if ($Inline == ''){
				$Imgpos = ($offset < 10000) ? "float:left;" : "float:right;";
			}
			$image['style'] = "style='".$Imgpos."width:".$ImgW."px; height:".$ImgH."px; ".$padd.$trans."'";

			// image id found, get the image location
			if (!$notfound && $relId) {
				$reader = new XMLReader();
				if ($this->HF == ''){
					$reader->XML($this->rels_xml->saveXML());
				} else if ($this->HF == 'H1'){
					$reader->XML($this->head1_rels_xml->saveXML());
				} else if ($this->HF == 'H2'){
					$reader->XML($this->head2_rels_xml->saveXML());
				} else if ($this->HF == 'H3'){
					$reader->XML($this->head3_rels_xml->saveXML());
				} else if ($this->HF == 'F1'){
					$reader->XML($this->foot1_rels_xml->saveXML());
				} else if ($this->HF == 'F2'){
					$reader->XML($this->foot2_rels_xml->saveXML());
				} else if ($this->HF == 'F3'){
					$reader->XML($this->foot3_rels_xml->saveXML());
				}
				while ($reader->read()) {
					if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name=='Relationship') {
						if($reader->getAttribute("Id") == $relId) {
							$link = "word/".$reader->getAttribute('Target');
							break;
						}
					}
				}
    			$zip = new ZipArchive();
    			$im = null;
    			if (true === $zip->open($this->file)) {
        			$image['image'] = $this->createImage($zip->getFromName($link), $relId, $link, $Icrop);
    			}
    			$zip->close();
    			return $image;
			}
		}

		return null;
	}

	/**
	 * Creates an image in the filesystem
	 *  
	 * @param objetc $image - The image object
	 * @param string $relId - The image relationship Id
	 * @param string $name - The image name
	 * @param array $crop - Details of a cropped image
	 * @return Array - With image tag definition
	 */
	private function createImage($image, $relId, $name, $crop)
	{
		static $Ccount = 1;
		$fname = '';
		$arr = explode('.', $name);
		$l = count($arr);
		$ext = strtolower($arr[$l-1]);
		
		if (!is_dir($this->tmpDir)){
			mkdir($this->tmpDir, 0755, true);
		}
		if ($ext == 'emf' OR $ext == 'wmf'){
			$ftname = $this->tmpDir.'/'.$relId.$this->HF.'.'.$ext;
			$tfile = fopen($ftname, "w");
			fwrite($tfile, $image);
			fclose($tfile);
			$fname = $this->tmpDir.'/'.$relId.$this->HF.'.jpg';
			if ($ext == 'wmf'){ // Note that Imagick will only convert '.wmf' files (NOT '.emf' files)
				$imagick = new Imagick();
				$imagick->setresolution(300, 300);
				$imagick->readImage($ftname);
				$imagick->resizeImage(1000,0,Imagick::FILTER_LANCZOS,1);
				$imagick->setImageFormat('jpg');
				$imagick->writeImage($fname);
			}
			

		} else {
			$im = imagecreatefromstring($image);
			if (isset($crop['left'])){
				$Iwidth = imagesx($im);
				$Iheight = imagesy($im);
				$Cx = round($crop['left'] * $Iwidth, 0);
				$Cy = round($crop['top'] * $Iheight, 0);
				$Cw = round($crop['width'] * $Iwidth, 0);
				$Ch = round($crop['height'] * $Iheight, 0);
				$im = imagecrop($im, ['x' => $Cx, 'y' => $Cy, 'width' => $Cw, 'height' => $Ch]);
				$fname = $this->tmpDir.'/'.$relId.$this->HF.$Ccount.'.'.$ext;
				$Ccount++;
			} else {		
				$fname = $this->tmpDir.'/'.$relId.$this->HF.'.'.$ext;
			}

			switch ($ext) {
				case 'png':
					// Ensure alpha channel is preserved
					imagesavealpha($im, true);
					// Set alpha blending mode
					imagealphablending($im, false);
					// Output PNG with full alpha channel
					imagepng($im, $fname, 9, PNG_ALL_FILTERS);
//					$contentType = 'image/png';
//					imagepng($im, $fname);
					break;
				case 'bmp':
					imagebmp($im, $fname);
					break;
				case 'gif':
					imagegif($im, $fname);
					break;
				case 'jpeg':
				case 'jpg':
					imagejpeg($im, $fname);
					break;
				case 'webp':
					imagewebp($im, $fname);
					break;
				default:
					return null;
			}
			imagedestroy($im);
			$fname = $fname."?id=".time();
		}
		return $fname;
	}

	/**
	 * CHECKS IF ELEMENT IS AN HYPERLINK
	 *  
	 * @param XML $xml - The XML node
	 * @return Array - Hyperlink open and closing tag definition
	 */
	private function getHyperlink(&$xml,$RelF)
	{
		$ret = array('open'=>'<ul>','close'=>'</ul>');
		$link = $tar = '';
		static $rels_end = null ;
		static $rels_foot = null ;
		if($xml->hasAttributes) {
			$attribute = "";
			$target = "";
			while($xml->moveToNextAttribute()) {
				if($xml->name == "r:id"){  // check for external hyperlinks
					$attribute = $xml->value;
				}
				if($xml->name == "w:tgtFrame"){  // check for target frame
					$target = $xml->value;
					if ($target == '_self'){
						$tar="target='_self'";
					}
					if ($target == '_blank'){
						$tar="target='_blank' rel='noopener noreferrer'";
					}
					if ($target == '_top'){
						$tar="target='_top'";
					}
					if ($target == '_parent'){
						$tar="target='_parent'";
					}
				}
				if($xml->name == "w:anchor"){  // check for internal bookmark links
					$internalT = $xml->value;
					if (substr($internalT,0,1) == '_'){
					$internal = substr($internalT,1);
				} else {
					$internal = $internalT;
				}

					$this->anchor[$internal] = 'Y';
				}
			}
			
			if($attribute != "") {
				$reader = new XMLReader();
				if ($RelF == 'P'){
					$reader->XML($this->rels_xml->saveXML());
				} else if($RelF == 'F'){
					if(!$rels_foot){
						$zip = new ZipArchive();
						$_foot_rels = 'word/_rels/footnotes.xml.rels';
						if (true === $zip->open($this->file)) {
							//Get the footnotes relationships file
							if (($index = $zip->locateName($_foot_rels)) !== false) {
								$foot_rels = $zip->getFromIndex($index);
							}
							$zip->close();
						}
						$enc = mb_detect_encoding($foot_rels);
						$this->setXmlParts($rels_foot, $foot_rels, $enc);
						if($this->debug) {
							echo "<br>XML File : word/_rels/footnotes.xml.rels<br>";
							echo "<textarea style='width:100%; height: 200px;'>";
							echo $rels_foot->saveXML();
							echo "</textarea>";
						}
					}
					$reader->XML($rels_foot->saveXML()); //Get the footnote hyperlinks from the footnotes relationships file

				} else if($RelF == 'E'){
					if(!$rels_end){
						$zip = new ZipArchive();
						$_end_rels = 'word/_rels/endnotes.xml.rels';
						if (true === $zip->open($this->file)) {
							//Get the endnotes relationships file
							if (($index = $zip->locateName($_end_rels)) !== false) {
								$end_rels = $zip->getFromIndex($index);
							}
							$zip->close();
						}
						$enc = mb_detect_encoding($end_rels);
						$this->setXmlParts($rels_end, $end_rels, $enc);
						if($this->debug) {
							echo "<br>XML File : word/_rels/endnotes.xml.rels<br>";
							echo "<textarea style='width:100%; height: 200px;'>";
							echo $rels_end->saveXML();
							echo "</textarea>";
						}
					}
					$reader->XML($rels_end->saveXML()); //Get the endnote hyperlinks from the endnotes relationships file

				}
				
				while ($reader->read()) {
					if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name=='Relationship') {
						if($reader->getAttribute("Id") == $attribute) {
							$link = $reader->getAttribute('Target');
							break;
						}
					}
				}
			}
		}
		
		if($link != "") { // external hyperlinks
			$ret['open'] = "<a href='".$link."' ".$tar.">";
			$ret['close'] = "</a>";
		} else { // internal bookmark links
			$ret['open'] = "<a id='R".$internal."' style='text-decoration:none;' href='#".$internal."'>";
			$ret['close'] = "</a>";
		}
		
		return $ret;
	}




	/**
	 * PROCESS PARAGRAPH CONTENT
	 *  
	 * @param XML $xml - The XML node
	 * @param String $Tstyle - The name of the table style
	 * @return String - The HTML code of the paragraph
	 */
	private function getParagraph(&$paragraph,$Tstyle)
	{
		$zst = array();
		$text = $BookMk = $BookRet = $Pstyle = $zst[0] = $CBdef = $CBdef = $Mpara = $MP = $Malign = $Ttext = $IflipH = $IflipV = '';
		$list_format = $Tlist_format = array();
		$zzz = $text;
		$zstc = 1;
		$ac = 'N';
		$Pformat = 'N';
		$wr = $offset = 0;
		$dr = $TXT = $xf = $tp = $ex = $tc = $RT = $offset = $ta = $padd = $trans = '';
		
		$Dstyle = $this->getListFormating($paragraph,$Tstyle); //default styles for the document
		if (!isset($Dstyle['Pform'])){
			$Dstyle['Pform'] = '';
		}
		// loop through paragraph dom
		while ($paragraph->read()) {
			// look for elements
			if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'mc:AlternateContent') {
				$ac = 'Y';
			}
			if ($paragraph->nodeType == XMLREADER::END_ELEMENT && $paragraph->name === 'mc:AlternateContent') {
				$ac = 'N';
				$xf = $ex = $RT = $Irot = $IflipV = $offset = $IflipH = $padd = $trans = '';
			}
			if ($paragraph->nodeType == XMLREADER::END_ELEMENT && $paragraph->name === 'w:r') {
				--$wr;
			}
			if ($paragraph->nodeType == XMLREADER::END_ELEMENT && $paragraph->name === 'w:drawing') {  //checks of there is an image or textbox
				$dr = '';
				$Ttext .= "</div>";
			}
			if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:drawing'){
				$dr = 'Y';
			}
			if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:p'){
				$tp = 'Y';
			}
			if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name == "wp:extent") { // Get textbox size
				$ImgW1 = (int)$paragraph->getAttribute("cx");
				$ImgW = round($ImgW1/9000);
				$ImgH1 = (int)$paragraph->getAttribute("cy");
				$ImgH = round($ImgH1/9000);
				
				$Ppos = round($ImgW1/2) + floatval($offset);
				$PR1 = round($this->Pagewidth[$this->snt] * 500 / 3); //Define split point for left / centre text box
				$PR2 = $PR1 * 2; //Define split point for centre / right text box
				if($Ppos < $PR1){
					$TBpos = "width:".$ImgW."px; float:left; ";
				} else if($Ppos > $PR2){
					$TBpos = "width:".$ImgW."px; float:right; ";
				} else {
					$TBpos = "width:".$ImgW."px; margin: auto; ";
				}
				if ($ta == 'center'){
					$TBpos = "width:".$ImgW."px; margin: auto; ";					
				}
			}
			if ($paragraph->name == "w:lastRenderedPageBreak"){
				if ($this->snti == 'Y'){
					++$this->snt;  // Found start of next section
					$this->snti = 'N';		
				}
			} 
			if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name == "w:pgSz"){
				$this->snti = 'Y';
			} 
			if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name == "wp:posOffset" AND $ex == '') {
				$offset = $paragraph->expand()->textContent;  // Checks to see where the textbox is on the page
				$ex = 'Y';
			}
			if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name == "wp:align") {
				$ta = $paragraph->expand()->textContent;  // Checks to see if the textbox is in the centre of the page the page
			}
			if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === "a:xfrm" and $xf == '') { // Check if textbox is rotated and/or flipped
				if ($paragraph->getAttribute("rot")){
					$Irot = $paragraph->getAttribute("rot")/60000;
					$RT = 'Y';
				}
				if ($paragraph->getAttribute("flipH")){
					$IflipH = $paragraph->getAttribute("flipH");
					$RT = 'Y';
				}
				if ($paragraph->getAttribute("flipV")){
					$IflipV = $paragraph->getAttribute("flipV");
					$RT = 'Y';
				}
				$xf = 'Y';
				if ($RT == 'Y'){
					$trans = "transform:";
					if ($Irot <> ''){
						$trans .= " rotate(".$Irot."deg)";
					}
					if ($IflipH <> ''){
						$trans .= " scaleX(-1)";
					}
					if ($IflipV <> ''){
						$trans .= " scaleY(-1)";
					}
					$trans .= "; ";
					// find amount of margin needed for rotated image
					if ($Irot <> ''){
						if($Irot == '90' OR $Irot == '270'){
							if($ImgW > $ImgH){
								$diff = ceil(($ImgW - $ImgH)/2);
								$padd = "margin:".$diff."px -".$diff."px ".$diff."px -".$diff."px; ";				
							} else if($ImgH > $ImgW){
								$diff = ceil(($ImgW - $ImgH)/2);
								$padd = "margin:-".$diff."px ".$diff."px -".$diff."px ".$diff."px; ";				
							}
						} else {
							if (($Irot > 0 AND $Irot <90) OR ($Irot > 180 AND $Irot <270)){
								if ($Irot > 180){
									$Irot = $Irot - 180;
								}
								$Rcot = deg2rad($Irot);
								$Rwidth = $ImgW * cos($Rcot) + $ImgH * sin($Rcot);
								$Rheight = $ImgW * sin($Rcot) + $ImgH * cos($Rcot);
							} else {
								if ($Irot > 270){
									$Irot = $Irot - 270;
								} else {
									$Irot = $Irot - 90;
								}
								$Rcot = deg2rad($Irot);
								$Rwidth = $ImgH * cos($Rcot) + $ImgW * sin($Rcot);
								$Rheight = $ImgH * sin($Rcot) + $ImgW * cos($Rcot);
							}
							if ($Rwidth > $ImgW){
								$Pwidth = ceil((($Rwidth - $ImgW) / 2));
							} else {
								$Pwidth = - ceil((($ImgW - $Rwidth) / 2));
							}
							if ($Rheight > $ImgH){
								$Pheight = ceil((($Rheight - $ImgH) / 2));
							} else {
								$Pheight = - ceil((($ImgH - $Rheight) / 2));
							}
							$padd = "margin:".$Pheight."px ".$Pwidth."px ".$Pheight."px ".$Pwidth."px; ";
						}
					}
				}
				$Ttext .= "<div style='".$trans.$padd.$TBpos." '>";
			}
			if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:r'){
				++$wr;
				if($wr == 1){
					if (!isset($list_format['style'])){
						$list_format['style'] = '';
					}
					if (!isset($list_format['Dcap'])){
						$list_format['Dcap'] = '';
					}
					$Pelement = array();
					$Pelement = $this->checkFormating($paragraph,$list_format['style'],$list_format['Dcap']); 
					//check if this element is a page break and if so ignore this element
					$tt = 'N';
					if (!isset($Pelement['Pbreak'])){
						if ($Pelement['text'] <> ''){
							$TXT = 'Y';
						}
						if ($Pformat == 'Y'){
							if ($list_format['Pform'] <> ''){
								$text .= "<p".$BookMk.$list_format['Pform'].">"; // brings in paragraph formatting
							} else {
								$text .= "<p".$BookMk.$Dstyle['Pform'].">";
							}
							if(isset($list_format['listnum'])){
								$text .= $Pelement['style'].$list_format['Lnum']."</style>";
								$tt = 'Y';
							}
							$Pformat = 'D';
						}
						if ($Pformat == 'N'){
							$text .= "<p".$BookMk.$Dstyle['Pform'].">";
							$Pformat = 'D';
						}
						$zst[$zstc] = $Pelement['style'];
						if ($zst[$zstc] != $zst[$zstc-1]){
							if ($zstc > 1){
								$text .= "</span>".$Pelement['style'];
							} else if (isset($Pelement['text']) AND $tt <> 'Y'){
								$text .= "  ".$Pelement['style'];
							}
							$zstc++;
						}
						if (isset($Pelement['CRtext'])){
							$CRlink = $Pelement['CRtext'];
						}
						if (isset($Pelement['text'])){
							if (isset($CRlink)){
								$text .= "<a id='R".$CRlink."' href='#".$CRlink."'>".$Pelement['text']."</a>";
								$CRlink = '';
							} else {
								if ($Pelement['text'] == ' '){
									$Pelement['text'] = "&nbsp;";
								}
								$text .= $Pelement['text']; 
							}
						}
					}
				} else if ($dr == 'Y'){
					$Telement = $this->checkFormating($paragraph,$list_format['style'],
					$list_format['Dcap']);
					if ($tp == 'Y'){
						if ($tc == 'Y'){
							$Ttext .= "</span></p> \n";
						}
						if(!isset($Tlist_format['Pform'])){
							$Tlist_format['Pform'] = '';
						}
						$Ttext .= "<p ".$Tlist_format['Pform']."> ".$Telement['style'].$Telement['text'];
					} else {
						$Ttext .= $Telement['text'];
					}
					$tc = 'Y';
					$tp = '';
				} 
			}else if(($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:pPr') AND $dr == 'Y') { // Get list and paragraph formatting for text boxes and images
					$Tlist_format = $this->getListFormating($paragraph,$Tstyle);
			} else if(($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:pPr') AND $ac == 'N') { // Get list and paragraph formatting
				$list_format = $this->getListFormating($paragraph,$Tstyle);
				$Pformat = 'Y';
			} else if($paragraph->name == "w:bookmarkStart") { // check for internal bookmark link and its return
				$BM = $paragraph->getAttribute("w:name");
				if (substr($BM,0,1) == '_'){
					$BookL = substr($BM,1);
				} else {
					$BookL = $BM;
				}
				if (isset($this->anchor[$BookL])){
					if ($BM  <> '_GoBack'){
						$BookMk = " id='".$BookL."'";
						$BookRet = "&nbsp;<a href='#R".$BookL."'><sup>[return]</a>";
					}
				}
			} else if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:hyperlink') {
				if ($Pformat == 'Y'){ // Add in paragraph formatting for Bookmark links 
					if ($list_format['Pform'] <> ''){
						$text = "<p".$list_format['Pform'].">"; // brings in paragraph formatting
					} else {
						$text .= "<p".$Dstyle['Pform'].">";
					}
					if($list_format['listnum']){
						$Pelement = array();
						$Pelement = $this->checkFormating($paragraph,$list_format['style'],$list_format['Dcap']); // Get inline style for list numbering
						$text .= $Pelement['style'].$list_format['Lnum']."</style>";
					}
					$Pformat = 'D';
				}
				if ($Pformat == 'N'){
					$text .= "<p".$Dstyle['Pform'].">";
					$Pformat = 'D';
				}
				$hyperlink = $this->getHyperlink($paragraph,'P'); // Add in hyperlinks and bookmarks
				$text .= $hyperlink['open'];
				$Pelement2 = $this->checkFormating($paragraph,$Pstyle,$list_format['Dcap']);
				$zst[$zstc] = $Pelement2['style'];
				if ($zst[$zstc] != $zst[$zstc-1]){
					if ($zstc > 1){
						$text .= "</span>".$Pelement2['style'];
					} else {
						$text .= $Pelement2['style'];
					}
					$zstc++;
				}
				$text .= $Pelement2['text'];
				$text .= $hyperlink['close'];
				$paragraph->next();
			} else if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:checkBox') {
				$cb2 = new SimpleXMLElement($paragraph->readOuterXML());
				foreach ($cb2->children('w',true) as $ch) {
					if (in_array($ch->getName(), ['default']) ) {
						$CBdef = $ch['val'];
					}
				}
				if ($CBdef == '0'){
					$text .= "<input type=\"checkbox\">";
				} else {
					$text .= "<input type=\"checkbox\" checked>";
				}
			} else if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'm:oMathPara') {
				$Mpara = 'Y';
			} else if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'm:jc') {
				$Malign = $paragraph->getAttribute("m:val");
			} else if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'm:oMath') {
				$Melement = $this->getMaths($paragraph, $Mpara, $Malign);
				$text .= $Melement;
				$MP = 'Y';
				$this->Maths = 'Y';
			}
		}
		if ($zzz == $text){
			$text .= "<p".$Dstyle['Pform'].">&nbsp;</p> \n";
		} else {
			if (substr($text,-1) == '>'){
				$text .= "&nbsp;</span>".$BookRet."</p> \n";
			} else if ($MP == 'Y'){
				$text .= "  \n";
			} else {
				$text .= "</span>".$BookRet."</p> \n";
			}
		}
//		if ($TXT == '' AND $Ttext <> ''){
			$text .= $Ttext;
//		} 
		if (isset($list_format['hr'])){
			$text .= $list_format['hr'];
		}
		return $text;
	}
			

// ------------------- END OF PARAGRAPH PROCESSING ------------------------

// ------------------- START OF MATHS PROCESSING ------------------------

	/**
	 * CHECKS THE MATHS FUNCTION OF A GIVEN ELEMENT
	 * 
	 * @param XML $xml - The XML node
	 * @param String $Pstyle - The name of the paragraph style
	 * @param String $Dcap - The type of drop capital if it exists
	 * @return Array - The elements styling and text
	 */
	private function getMaths(&$xml, $Mpara, $Malign)
	{	
		
		$Mintegral = $MintSS = $MIsubL = $MIsupL = $MIsup = $MIsub = $Msub = $Msup = $Echr = $Mlimit = $Mchr = $Bchr = $MuO = $DSmaths = $MaccC = $MaccT = $MaccC = $MGC = $MGL = $Mfunc= $MFnb = $Mear = $Me = $Begch = $Endch = $MdivT = $MuON = $Limpos = $Mmat = $Matpos = $Mmsub = '';
		$Mroot = $Elevel = $Mden = $Mnum = 0;
		$MRexp = array();
		if ($Mpara == 'Y'){
			$Dmaths = "\[";
		} else {
			$Dmaths = "\(";
		}
		if ($Malign == 'left'){
			$Dmaths .= "\begin{flalign}\;";
		}
		if ($Malign == 'right'){
			$Dmaths .= "\begin{flalign} && ";
		}
		$node = trim($xml->readOuterXML());
		$reader = new XMLReader();
		$reader->XML($node);
		while ($reader->read()) {
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:naryPr') { //get maths integral funtion
				$mf = new XMLReader;
				$mf->xml(trim($reader->readOuterXML()));
				while ($mf->read()) {
					if ($mf->nodeType == XMLREADER::ELEMENT && $mf->name === 'm:chr') { //get integral function type
						$Mintegral = $mf->getAttribute("m:val");
					}
					if ($mf->nodeType == XMLREADER::ELEMENT && $mf->name === 'm:limLoc') { //get integral function limits
						$MintSS = $mf->getAttribute("m:val");
						if ($MintSS == 'undOvr'){
							$MuO = '\limits';
						}
						if ($MintSS == 'subSup'){
							$MuON = '\nolimits';
						}
						if ($MintSS == 'subSup' OR $MintSS == 'undOvr'){
							$MIsubL = 'Y';
							$MIsupL = 'Y';
						} else {
							$MIsubL = 'N';
							$MIsupL = 'N';
						}
					}
					if ($mf->nodeType == XMLREADER::ELEMENT && $mf->name === 'm:subHide') {  
						$MSbH = $mf->getAttribute("m:val");
						if ($MSbH == 1){
							$MIsubL = 'N';
						}
					}
					if ($mf->nodeType == XMLREADER::ELEMENT && $mf->name === 'm:supHide') {  
						$MSpH = $mf->getAttribute("m:val");
						if ($MSpH == 1){
							$MIsupL = 'N';
						}
					}
					if ($mf->nodeType == XMLREADER::ELEMENT && $mf->name === 'm:grow') { //get integral function limits
						$Mgrow = $mf->getAttribute("m:val");
						if ($Mgrow == '1'){
							$MIsubL = 'Y';
							$MIsupL = 'Y';
						} else {
							$MIsubL = 'N';
							$MIsupL = 'N';
						}
					}
				}
				if ($Mintegral == ''){
					$Dmaths .= "\int".$MuO;
				} else {
					if ($Mintegral == '∬'){
						$Dmaths .= "\iint".$MuO;
					} else if ($Mintegral == '∭'){
						$Dmaths .= "\iiint".$MuO;
					} else if ($Mintegral == '∮'){
						$Dmaths .= "\oint".$MuO;
					} else if ($Mintegral == '∯'){
						$Dmaths .= "\oiint".$MuO;
					} else if ($Mintegral == '∰'){
						$Dmaths .= "\oiiint".$MuO;
					} else if ($Mintegral == '∑'){
						$Dmaths .= "\sum".$MuON;
						if ($MIsubL <> 'N'){
							$MIsubL = 'Y';
						}
					} else if ($Mintegral == '∏'){
						$Dmaths .= "\prod".$MuON;
					} else if ($Mintegral == '∐'){
						$Dmaths .= "\coprod".$MuON;
					} else if ($Mintegral == '⋁'){
						$Dmaths .= "\bigvee".$MuON;
					} else if ($Mintegral == '⋀'){
						$Dmaths .= "\bigwedge".$MuON;
					} else if ($Mintegral == '⋃'){
						$Dmaths .= "\bigcup".$MuON;
					} else if ($Mintegral == '⋂'){
						$Dmaths .= "\bigcap".$MuON;
					}
				}
				$MuON = $MuO = '';
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:sub') { 
				if ($MIsubL == 'Y'){ //get start of maths subscript
					$Dmaths .= "_{";
					$MsubF = 'Y';
					$MIsub = '';
				} else if ($MIsubL == 'N'){
					$Dmaths .= "";
					$MIsubL = '';
				} else if ($MIsubL == ''){
					$Dmaths .= "_{";
				}
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:sub') {
				if ($MIsubL == '' OR $MIsubL == 'Y'){
					$Dmaths .= "}";
				} else {
					$MIsubL = '';
				}
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:sup') { //get start of maths superscript
				if ($MIsupL == 'Y'){
					$Dmaths .= "^{";
					$MsupF = 'Y';
					$MIsup = '';
				} else if ($MIsupL == 'N'){
					$Dmaths .= "";
					$MIsupL = '';
				} else if ($MIsupL == ''){
					$Dmaths .= "^{";
				}				
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:sup') {
				if ($MIsupL == '' OR $MIsupL == 'Y'){
					$Dmaths .= "}";
				} else {
					$MIsupL = '';
				}
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:d') {
				$Bchr = $Echr = $Begch = $Endch = '';
				$mbrack = new XMLReader;
				$mbrack->xml(trim($reader->readOuterXML()));
				while ($mbrack->read()) {
					if ($mbrack->name == 'm:begChr') { //check for alternative beginning bracket chr 
						$Bchr = $mbrack->getAttribute("m:val");
						$Begch = 'Y';
					}
					if ($mbrack->name == 'm:endChr') { //check for alternative ending bracket chr 
						$Echr = $mbrack->getAttribute("m:val");
						$Endch = 'Y';
					}
				}
				$Dmaths .= "\left";
				if ($Begch == 'Y'){
					if ($Bchr == ''){
						$Dmaths .= ".";
					} else if ($Bchr == '{'){ //left brace
						$Dmaths .= "\{ ";
					} else if ($Bchr == '}'){ //right brace
						$Dmaths .= "\} ";
					} else if ($Bchr == '〈' OR $Bchr == '⟨'){ //left angle
						$Dmaths .= "\langle ";
					} else if ($Bchr == '〉' OR $Bchr == '⟩'){ //right angle
						$Dmaths .= "\\rangle ";
					} else if ($Bchr == '⌊'){ //left floor
						$Dmaths .= "\lfloor ";
					} else if ($Bchr == '⌋'){ //right floor
						$Dmaths .= "\\rfloor ";
					} else if ($Bchr == '⌈'){ //left ceil
						$Dmaths .= "\lceil ";
					} else if ($Bchr == '⌉'){ //right ceil
						$Dmaths .= "\\rceil ";
					} else if ($Bchr == '‖' OR $Bchr == '⟦' OR $Bchr == '⟧'){ //double pipe
						$Dmaths .= "\Vert ";
					} else {
						$Dmaths .= $Bchr;
					}
				} else {
					$Bchr = "(";
					$Dmaths .= $Bchr;
				} 
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:d') {
				if ($Endch == 'Y'){
					$Endch = '';
					$Dmaths .= "\\right";
					if ($Echr == ''){
						$Dmaths .= ".";
					} else if ($Echr == '{'){ //left brace
						$Dmaths .= "\{ ";
					} else if ($Echr == '}'){ //right brace
						$Dmaths .= "\} ";
					} else if ($Echr == '〈' OR $Echr == '⟨'){ //left angle
						$Dmaths .= "\langle ";
					} else if ($Echr == '〉' OR $Echr == '⟩'){ //right angle
						$Dmaths .= "\\rangle ";
					} else if ($Echr == '⌊'){ //left floor
						$Dmaths .= "\lfloor ";
					} else if ($Echr == '⌋'){ //right floor
						$Dmaths .= "\\rfloor ";
					} else if ($Echr == '⌈'){ //left ceil
						$Dmaths .= "\lceil ";
					} else if ($Echr == '⌉'){ //right ceil
						$Dmaths .= "\\rceil ";
					} else if ($Echr == '‖' OR $Echr == '⟦' OR $Echr == '⟧'){ //double pipe
						$Dmaths .= "\Vert ";
					} else {
						$Dmaths .= $Echr." ";
					}
				} else {
					$Echr = ")";
					$Dmaths .= "\\right";
					$Dmaths .= $Echr." ";
				}
				$Bchr = '';
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:eqArr') {
				$Mear = 'Y';
				if ($Begch == ''){
					$Dmaths .= "\\left.\begin{matrix}";
				} else {
					$Dmaths .= "\begin{matrix}";
				}
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:eqArr') {
				$Mear = '';
				if ($Endch == ''){
					$Dmaths .= "\\end{matrix}\\right.";
				} else {
					$Dmaths .= "\\end{matrix}";
				}
			}
			if ($Mroot == 0){
				if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:deg') {
					$mroot = new XMLReader;
					$mroot->xml(trim($reader->readOuterXML()));
					while ($mroot->read()) {
						if ($mroot->name == 'm:t') { //get level of root 
							$Tmptext1 = htmlentities($mroot->expand()->textContent);
							$Mroot .= preg_replace('~(?<=\s)\s~', '&nbsp;', $Tmptext1);
						}
					}
					$Mroot = intval($Mroot);
					$Dmaths .= "\sqrt[".$Mroot."]";
				}
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:degHide') {
				$Mtroot = $reader->getAttribute("m:val");
				$Dmaths .= "\sqrt";
				$Mroot = 2;
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:func') {
				$Mfunc = 'Y';
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:func') {
				$Mfunc = '';
				$Dmaths .= "\,";
			}
			if ($MGL == ''){
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:lim') {
				$mlim = new XMLReader;
				$mlim->xml(trim($reader->readOuterXML()));
				while ($mlim->read()) {
					if ($mlim->name == 'm:t') { //get limit value 
						$Tmptext1 = htmlentities($mlim->expand()->textContent);
						$Mlimit .= preg_replace('~(?<=\s)\s~', '&nbsp;', $Tmptext1);
					}
				}
			}
			}

			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:acc') {
				$macc = new XMLReader;
				$macc->xml(trim($reader->readOuterXML()));
				$MaccT = '';
				while ($macc->read()) {
					if ($macc->name == 'm:chr') { 
						$MaccC = $macc->getAttribute("m:val");
					}
					if ($macc->name == 'm:t') { //get limit value 
						$Tmptext1 = htmlentities($macc->expand()->textContent);
						$MaccT .= preg_replace('~(?<=\s)\s~', '&nbsp;', $Tmptext1);
					}
				}
				switch($MaccC) {
					case "̃":
						$Dmaths .= "\\tilde{".$MaccT."}";
						break;
					case "→":
						$Dmaths .= "\hat{".$MaccT."}";
						break;
					case "̌":
						$Dmaths .= "\check{".$MaccT."}";
						break;
					case "→":
						$Dmaths .= "\vec{".$MaccT."}";
						break;
					case "̅":
						$Dmaths .= "\bar{".$MaccT."}";
						break;
					case "́":
						$Dmaths .= "\acute{".$MaccT."}";
						break;
					case "̀":
						$Dmaths .= "\grave{".$MaccT."}";
						break;
					case "̆":
						$Dmaths .= "\breve{".$MaccT."}";
						break;
					case "̇":
						$Dmaths .= "\dot{".$MaccT."}";
						break;
					case "̈":
						$Dmaths .= "\ddot{".$MaccT."}";
						break;
					case "⃛":
						$Dmaths .= "\dddot{".$MaccT."}";
						break;
					case "⃖":
						$Dmaths .= "\overleftarrow{".$MaccT."}";
						break;
					case "⃗":
						$Dmaths .= "\overrightarrow{".$MaccT."}";
						break;
					case "⃡":
						$Dmaths .= "\overleftrightarrow{".$MaccT."}";
						break;
					case "⃐":
						$Dmaths .= "\overset{\leftharpoonup}{".$MaccT."}";
						break;
					case "⃑":
						$Dmaths .= "\overset{\\rightharpoonup}{".$MaccT."}";
						break;
				}
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:acc') {
				$MaccC = '';
			}

			
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:borderBox') {
				$Dmaths .= "\boxed{";
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:borderBox') {
				$Dmaths .= "}";
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:bar') {
				$Dmaths .= "\underline{";
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:bar') {
				$Dmaths .= "}";
			}


			if ($MGL == ''){
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:groupChr') {
				$MGC = 'Y';
				$MchT = '';
				$Mpos = $Mchr = '';
				$mgch = new XMLReader;
				$mgch->xml(trim($reader->readOuterXML()));
				while ($mgch->read()) {
					if ($mgch->name == 'm:chr') { 
						$Mchr = $mgch->getAttribute("m:val");
					}
					if ($mgch->name == 'm:pos') { 
						$Mpos = $mgch->getAttribute("m:val");
					}
					if ($mgch->name == 'm:vertJc' AND $Mpos == '') { 
						$Mpos = $mgch->getAttribute("m:val");
					}
					if ($mgch->name == 'm:t') { 
						$Tmptext1 = htmlentities($mgch->expand()->textContent);
						$MchT .= preg_replace('~(?<=\s)\s~', '&nbsp;', $Tmptext1);
					}
				}
				if ($Mchr == "←" AND $Mpos == 'top'){
					$Dmaths .= "\xleftarrow[".$MchT."]{ }";
				} else if ($Mchr == "←" AND $Mpos == 'bot'){
					$Dmaths .= "\xleftarrow{".$MchT."}\;";
				} else if ($Mchr == "→" AND $Mpos == 'top'){
					$Dmaths .= "\xrightarrow[".$MchT."]{ }";
				} else if ($Mchr == "→" AND $Mpos == 'bot'){
					$Dmaths .= "\xrightarrow{".$MchT."}\;";
				} else if ($Mchr == "⇐" AND $Mpos == 'top'){
					$Dmaths .= "\xLeftarrow[".$MchT."]{ }";
				} else if ($Mchr == "⇐" AND $Mpos == 'bot'){
					$Dmaths .= "\xLeftarrow{".$MchT."}\;";
				} else if ($Mchr == "⇒" AND $Mpos == 'top'){
					$Dmaths .= "\xRightarrow[".$MchT."]{ }";
				} else if ($Mchr == "⇒" AND $Mpos == 'bot'){
					$Dmaths .= "\xRightarrow{".$MchT."}\;";
				} else if ($Mchr == "↔" AND $Mpos == 'top'){
					$Dmaths .= "\xleftrightarrow[".$MchT."]{ }";
				} else if ($Mchr == "↔" AND $Mpos == 'bot'){
					$Dmaths .= "\xleftrightarrow{".$MchT."}\;";
				} else if ($Mchr == "⇔" AND $Mpos == 'top'){
					$Dmaths .= "\xLeftrightarrow[".$MchT."]{ }";
				} else if ($Mchr == "⇔" AND $Mpos == 'bot'){
					$Dmaths .= "\xLeftrightarrow{".$MchT."}\;";
				} else if ($Mchr == "⏞" AND $Mpos == 'top'){
					$Dmaths .= "\overbrace{".$MchT."}";
				} else if ($Mchr == "" AND $Mpos == ''){
					$Dmaths .= "\underbrace{".$MchT."}\;";
				}
			}
			}

			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:groupChr') {
				$MGC = '';
			}
			if ($Mfunc == ''){
			if (($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:limUpp') OR ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:limLow')) {
				$MLchr = $MLpos = $mlim = $MluT = $MlimT = '';
				$MGL = 'Y';
				$mlup = new XMLReader;
				$mlup->xml(trim($reader->readOuterXML()));
				while ($mlup->read()) {
					if ($mlup->name == 'm:chr') { 
						$MLchr = $mlup->getAttribute("m:val");
					}
					if ($mlup->name == 'm:pos') { 
						$MLpos = $mlup->getAttribute("m:val");
					}
					if ($mlup->name == 'm:lim') { 
						$mlim = 'Y';
					}
					if ($mlim == ''){
						if ($mlup->name == 'm:t') { 
							$Tmptext1 = htmlentities($mlup->expand()->textContent);
							$MluT .= preg_replace('~(?<=\s)\s~', '&nbsp;', $Tmptext1);
						}
					}
					if ($mlim == 'Y'){
						if ($mlup->name == 'm:t') { 
							$Tmptext2 = htmlentities($mlup->expand()->textContent);
							$MlimT .= preg_replace('~(?<=\s)\s~', '&nbsp;', $Tmptext2);
						}
					}
				}
				if ($MLchr == ''){
					$Dmaths .= "\underbrace{".$MluT ."}_\\text{".$MlimT."}";
					
				} else {
					$Dmaths .= "\overbrace{".$MluT ."}^\\text{".$MlimT."}";
				}
			}
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:limUpp'){
				$Limpos = 'top';				
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:limLow'){
				$Limpos = 'bot';				
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:limUpp' OR ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:limLow')) {
				$MGL = '';
			}
			if (($Me == 'Y' OR $Me == '2') AND $Mmat == '') {
				if (($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:e') AND $Mear =='' AND $Bchr <> '') {
					$Dmaths .= " | ";
					$Me = '';
				}
				if ($Me == 'Y'){
					$Me = '2';
				} else {
					$Me = '';
				}
			}		
					

			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:e') {
				++$Elevel;
				if ($Mroot <> 0){
					$MRexp[$Elevel] = 'Y';
					$Mroot = 0;
					$Dmaths .= "{";
				}
				if ($Mlimit <> ''){
					if($Limpos == 'top'){
						$Dmaths .= "^{".$Mlimit."}";
					} else {
						$Dmaths .= "_{".$Mlimit."}";
					}
					$Mlimit = '';
					$Limpos = '';
				}
				if ($Mmat == 'Y' AND $Mmsub == ''){
					if ($Matpos == 'E'){
						$Dmaths .= " & ";
					}
					$Matpos = 'E';
				}
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:e') {
				if (isset($MRexp[$Elevel])){
					if ($MRexp[$Elevel] == 'Y'){
						$Dmaths .= "}";
						$MRexp[$Elevel] = '';		
					}					
				}
				if ($Mear == 'Y'){
					$Dmaths .= "\\\ ";
				}
				--$Elevel;
				$Me = 'Y';
			}
			if (($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:sSub') OR ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:sSubSup') OR ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:sSup')) {
				$Mmsub = 'Y';
			}
			if (($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:sSub') OR ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:sSubSup') OR ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:sSup')) {
				$Mmsub = '';
			}

			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:type') {
				$Mtype = $reader->getAttribute("m:val");
				if ($Mtype == 'noBar'){
					$MFnb = 'Y';
				}
			}
			if ($reader->nodeType == XMLREADER::ELEMENT	&& $reader->name == 'm:type') {
				$MdivT = $reader->getAttribute("m:val");
			}
		
			if ($reader->nodeType == XMLREADER::ELEMENT	&& $reader->name == 'm:num') {
				++$Mnum;
				if ($MFnb == 'Y'){
					$Dmaths .= "{{";
				} else if ($MdivT == 'lin'){
					$Dmaths .= " {";
				} else if ($MdivT == 'skw'){
					$Dmaths .= " \\raise 4pt {";
				} else {
					$Dmaths .= " \\frac{";
				}
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:num') {
				--$Mnum;
				if ($MdivT == 'lin' OR $MdivT == 'skw'){
				$Dmaths .= "} / ";
				} else {
				$Dmaths .= "}";
				}
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:den') {
				++$Mden;
				if ($MFnb == 'Y'){
					$Dmaths .= "\atop{";
				} else if ($MdivT == 'skw'){
					$Dmaths .= " \lower 4pt {";
				} else {
					$Dmaths .= "{";
				}
				$MdivT = '';
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:den') {
				--$Mden;
				if ($MFnb == 'Y'){
					$Dmaths .= "}}";
				} else {
					$Dmaths .= "}";
				}
				$MFnb = '';
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:m') { // find matrix
				$Mmat = 'Y';
				$Dmaths .= "\begin{matrix}";
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:m') { // find end of matrix
				$Dmaths .= " \\end{matrix}";
				$Mmat = '';
				$Matpos = '';
			}
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name == 'm:mr') { // find start of matrix row
				if ($Matpos == 'R'){
					$Dmaths .= "\\\ ";
				}
			}
			if ($reader->nodeType <> XMLREADER::ELEMENT && $reader->name == 'm:mr') { // find end of matrix row
				$Matpos = 'R';
			}

			if ($Mroot == 0 AND $Mlimit == '' AND $MaccC == '' AND $MGC == '' AND $MGL == ''){
				if ($reader->name == 'm:t') { //get maths text 
					$Tmptext1 = htmlentities($reader->expand()->textContent);
					$MItext = preg_replace('~(?<=\s)\s~', '\;', $Tmptext1);
					$MItext = str_replace(' ','\;',$MItext);
					if ($Mfunc <> ''){
						if ($MItext == 'ln'){
							$Dmaths .= '\ln';
						} else if ($MItext == 'sin'){
							$Dmaths .= '\sin';
						} else if ($MItext == 'cos'){
							$Dmaths .= '\cos';
						} else if ($MItext == 'tan'){
							$Dmaths .= '\tan';
						} else if ($MItext == 'sinh'){
							$Dmaths .= '\sinh';
						} else if ($MItext == 'cosh'){
							$Dmaths .= '\cosh';
						} else if ($MItext == 'tanh'){
							$Dmaths .= '\tanh';
						} else if ($MItext == 'csch'){
							$Dmaths .= '\textnormal{csch}\,';
						} else if ($MItext == 'sech'){
							$Dmaths .= '\textnormal{sech}\,';
						} else if ($MItext == 'coth'){
							$Dmaths .= '\coth';
						} else if ($MItext == 'csc'){
							$Dmaths .= '\csc';
						} else if ($MItext == 'sec'){
							$Dmaths .= '\sec';
						} else if ($MItext == 'cot'){
							$Dmaths .= '\cot';
						} else if ($MItext == 'min'){
							$Dmaths .= '\min';
						} else if ($MItext == 'max'){
							$Dmaths .= '\max';
						} else if ($MItext == 'lim'){
							$Dmaths .= '\lim';
						} else if ($MItext == 'log'){
							$Dmaths .= '\log';
						} else {
							if ($MItext <> ''){
								$Dmaths .= "{".$MItext."}";	
							}								
						}
					} else {
						$MIt1 = str_replace('{','\\{',$MItext);
						$MIt2 = str_replace('}','\\}',$MIt1);
						$Dmaths .= $MIt2;
					}
				}
			}

		}
		if ($Malign == 'left'){
			$Dmaths .= " && \\end{flalign}";
		}
		if ($Malign == 'right'){
			$Dmaths .= "\;\\end{flalign}";
		}
		if ($Mpara == 'Y'){
			$Dmaths .= "\]";
		} else {
			$Dmaths .= "\)";
		}
		return $Dmaths;
	}


// ------------------- START OF TABLE PROCESSING ------------------------


	/**
	 * FIND NUMBER OF ROWS IN THE TABLE
	 *  
	 * @param XML $content - The XML node
	 * @return Array - The number of rows in the table and also details of which cells are part of a vertical merge
	 */
	private function getrows($content)
	{
		$ztext = new XMLReader;
		$ztext->xml($content);
		$Trow = 0;
		$hm = '';
		while ($ztext->read()) {
			if ($ztext->nodeType == XMLREADER::ELEMENT && $ztext->name === 'w:tr') { //find rows in the table
				$Trow++;
				$Tcol = 0;
			}
			if ($ztext->nodeType == XMLREADER::ELEMENT && $ztext->name === 'w:tc') { //find cells in the table
				$Tcol++;
				$cell[$Trow][$Tcol] = 1;
			}
			if ($ztext->nodeType <> XMLREADER::ELEMENT && $ztext->name === 'w:tc') {
				if ($hm <> ''){
					$Tcol = $Tcol + $hm - 1;
					$hm = '';
				}
			}				
			if ($ztext->nodeType == XMLREADER::ELEMENT && $ztext->name === 'w:gridSpan') { //find horizontal merged cells in the table
				$hm = $ztext->getAttribute("w:val");
			}
			
			if ($ztext->name === 'w:vMerge') { //find vertical merged cells in the table
				if ($ztext->getAttribute("w:val") == 'restart'){
					$mrow[$Tcol] = $Trow;
				} else {
					$cell[$mrow[$Tcol]][$Tcol]++;
					$cell[$Trow][$Tcol] = 0;
				}
			}
		}
		$ret['rows'] = $Trow;
		$ret['merge'] = $cell;

		return $ret;
	}



	/**
	 * PROCESS TABLE CONTENT
	 *  
	 * @param XML $xml - The XML node
	 * @return String - The HTML code of the table
	 */
	private function checkTableFormating(&$xml)
	{

		$Tstile = array();
		$TCcount = 0;
		$Twidth = 0;
		$Trow = 1;
		$Tstyle = $tabhead = $tabpos = $tabc = $Tabwid = $tabtext = $Tabtyp = $tts = '';
		$Colnum = 1;
		while ($xml->read()) {
			if ($xml->nodeType == XMLREADER::ELEMENT && $xml->name === 'w:tbl') { //Get number of rows in the table
				$Tinfo = $this->getrows(trim($xml->readOuterXML()));
				$Trows = $Tinfo['rows'];
				$Tmerge = $Tinfo['merge'];
			}
			if ($xml->name === 'w:tblStyle') { //Get table style
				$Tstyle = $xml->getAttribute("w:val");
			}

			if ($xml->nodeType == XMLREADER::ELEMENT && $xml->name === 'w:tblPr') { //Find start of table header
				$tabhead = 'Y';
			}
			if ($xml->nodeType == XMLREADER::END_ELEMENT && $xml->name === 'w:tblGrid') { //Find end of table header and grid
				if ($tabpos == 'center'){
					$tabc = "margin-left:auto; margin-right:auto; ";
				} else if ($tabpos == 'right'){
					$tabc = "margin-left:auto; margin-right:0px; ";
				}
				if ($this->Tcss == 'Y'){
					$table = "<div class='tab'><table style='border-collapse:collapse; width:100%;'>";
				} else if($Tabtyp == 'pct'){
					$tt = ceil($Tabwid / 50);
					$table = "<div class='tab'><table style='border-collapse:collapse; width:".$tt."%; ".$tts.$tabc."'>";
				} else if($Tabtyp == 'dxa'){
					if ($this->Tcss == 'F'){
						$tt = ceil($Tabwid / $this->MTFactor);
						$table = "<div class='tab'><table style='border-collapse:collapse; width:".$tt."px; ".$tts.$tabc."'>";
					} else {
						$tt = ceil(100 * $Tabwid / $this->EffPwidth[$this->snt]);
						if ($tt > 100){
							$tt = 100;
						}
						$table = "<div class='tab'><table style='border-collapse:collapse; width:".$tt."%; ".$tts.$tabc."'>";
					}
				} else {
					if ($this->Tcss == 'F'){
						$table = "<div class='tab'><table style='border-collapse:collapse; ".$tts.$tabc."'>";
					} else {
						$tt = ceil(100 * $Twidth / $this->EffPwidth[$this->snt]);
						if ($tt > 100){
							$tt = 100;
						}
						$table = "<div class='tab'><table style='border-collapse:collapse; width:".$tt."%; ".$tts.$tabc."'>";
					}
				}
				$tabhead = '';
			}
			if ($xml->name === 'w:tblpPr') { //Find out if text at the side of a table
				if ($xml->getAttribute("w:tblpXSpec") <> ''){
					 $tabtext = $xml->getAttribute("w:tblpXSpec");
				}
				if ($tabtext == 'right'){
					$tts = "float:right; margin-left:20px; ";
				} else {
					$tts = "float:left; margin-right:20px; ";
				}
			}
			if ($xml->name === 'w:tblW') { //Get table width
				$Tabwid = $xml->getAttribute("w:w");
				$Tabtyp = $xml->getAttribute("w:type");
			}
			if ($xml->name === 'w:jc' AND $tabhead == 'Y') { //Get table width
				$tabpos = $xml->getAttribute("w:val");
			}

			if ($xml->nodeType == XMLREADER::ELEMENT && $xml->name === 'w:tblBorders') { //Get table default borders
				$tc2 = new SimpleXMLElement($xml->readOuterXML());
				foreach ($tc2->children('w',true) as $ch) {
					if (in_array($ch->getName(), ['top','left','bottom','right','insideH','insideV']) ) {
						$line = $this->convertLine($ch['val']);
						if ($ch['color'] == 'auto'){
							$tbc = '000000';
						} else {
							$tbc = $ch['color'];
						}
						$zlinT = $ch['sz']/4;
						if ($zlinT >0 AND $zlinT <1){
							$zlinT = 1;
						}
						$Tname = $ch->getName();
						$Tstile[$Tname] = ":".$zlinT."px ".$line." #".$tbc.";";
					}
				}
			}
			
			
			if ($xml->nodeType == XMLREADER::ELEMENT && $xml->name === 'w:tblGrid') { //Get number of columns and their widths in the table
				$tr9 = new XMLReader;
				$tr9->xml(trim($xml->readOuterXML()));
				while ($tr9->read()) {
					if($tr9->name === 'w:gridCol'){
						$TCcount++;
						$Cwidth[$TCcount] = $tr9->getAttribute("w:w"); // column width
						$Twidth = $Twidth + $Cwidth[$TCcount]; // get width of the table
					}
				}
			}

			if ($xml->nodeType == XMLREADER::END_ELEMENT && $xml->name === 'w:tblGrid') { //
				$table .= "<colgroup>";
				$cc = 1;
				while ($cc <= $TCcount){
					$table .= "<col style=\"width: ".round($Cwidth[$cc]/$Twidth *100,2)."%;\">";
					++$cc;
				}
				$table .= "</colgroup><tbody>";
			}
			
			if ($xml->nodeType == XMLREADER::ELEMENT && $xml->name === 'w:tr') { //find and process a table row
				if (!isset($Tstile['top'])){
					if (isset($this->Rstyle[$Tstyle]['Btop'])){
						$Tstile['top'] = $this->Rstyle[$Tstyle]['Btop'];
					} else if (isset($this->Rstyle['TableNormal']['Btop'])){
						$Tstile['top'] = $this->Rstyle['TableNormal']['Btop'];
					} else {
						$Tstile['top'] = '';
					}
				}
				if (!isset($Tstile['left'])){
					if (isset($this->Rstyle[$Tstyle]['Bleft'])){
						$Tstile['left'] = $this->Rstyle[$Tstyle]['Bleft'];
					} else if (isset($this->Rstyle['TableNormal']['Bleft'])){
						$Tstile['left'] = $this->Rstyle['TableNormal']['Bleft'];
					} else {
						$Tstile['left'] = '';
					}
				}
				if (!isset($Tstile['bottom'])){
					if (isset($this->Rstyle[$Tstyle]['Bbottom'])){
						$Tstile['bottom'] = $this->Rstyle[$Tstyle]['Bbottom'];
					} else if (isset($this->Rstyle['TableNormal']['Bbottom'])){
						$Tstile['bottom'] = $this->Rstyle['TableNormal']['Bbottom'];
					} else {
						$Tstile['bottom'] = '';
					}
				}
				if (!isset($Tstile['right'])){
					if (isset($this->Rstyle[$Tstyle]['Bright'])){
						$Tstile['right'] = $this->Rstyle[$Tstyle]['Bright'];
					} else if (isset($this->Rstyle['TableNormal']['Bright'])){
						$Tstile['right'] = $this->Rstyle['TableNormal']['Bright'];
					} else {
						$Tstile['right'] = '';
					}
				} 
				if (isset($Tstile['right'])){
				}
				if (!isset($Tstile['insideH'])){
					if (isset($this->Rstyle[$Tstyle]['BinsideH'])){
						$Tstile['insideH'] = $this->Rstyle[$Tstyle]['BinsideH'];
					} else if (isset($this->Rstyle['TableNormal']['BinsideH'])){
						$Tstile['insideH'] = $this->Rstyle['TableNormal']['BinsideH'];
					} else {
						$Tstile['insideH'] = '';
					}
				}
				if (!isset($Tstile['insideV'])){
					if (isset($this->Rstyle[$Tstyle]['BinsideV'])){
						$Tstile['insideV'] = $this->Rstyle[$Tstyle]['BinsideV'];
					} else if (isset($this->Rstyle['TableNormal']['BinsideV'])){
						$Tstile['insideV'] = $this->Rstyle['TableNormal']['BinsideV'];
					} else {
						$Tstile['insideV'] = '';
					}
				}
				$tc = "";
				$ts = array();
				$table .= "<tr>";

				$tr = new XMLReader;
				$tr->xml(trim($xml->readOuterXML()));
				$Tcol = 1;
				$TCoffset = 0;
				while ($tr->read()) {
					$Cstyle = '';
					if ($tr->nodeType == XMLREADER::ELEMENT && $tr->name === 'w:tblCellMar') { //Get table row margin styles
						$tr3 = new SimpleXMLElement($tr->readOuterXML());
						foreach ($tr3->children('w',true) as $ch) {
							if (in_array($ch->getName(), ['top','left','bottom','right']) ) {
								$zlinM = round($ch['w']/$this->MTFactor);
								$Mname = "MR".$ch->getName();
								$this->Rstyle['Row'][$Mname] = ":".$zlinM."px;";
							}
						}
					}

					if ($tr->nodeType == XMLREADER::ELEMENT && $tr->name === 'w:tc') { //get cell borders and cell text and its formatting
						$TCoffset = 0;
						unset($ts);

						$tc = $this->processTableRow(trim($tr->readOuterXML()),$Tstyle);
					}
					$style = '';
					if ($tr->nodeType == XMLREADER::ELEMENT && $tr->name === 'w:tcPr') { //get cell border formatting
						$ts = $this->processTableStyle(trim($tr->readOuterXML()));
						if (isset($ts['left'])){  // set left border of a table cell
							$style .= " border-left".$ts['left'];
						} else{
							$style .= ($Tcol == 1) ? " border-left".$Tstile['left'] : " border-left".$Tstile['insideV'];
						}
						if (isset($ts['top'])){  // set the top border of a table cell
							$style .= " border-top".$ts['top'];
						} else{
							$style .= ($Trow == 1) ? " border-top".$Tstile['top'] : " border-top".$Tstile['insideH'];
						}
						if (isset($ts['bottom'])){  // set the bottom border of a table cell
							$style .= " border-bottom".$ts['bottom'];
						} else{
							if ($Tmerge[$Trow][$Tcol] > 1){  // set the bottom border of a table cell is a vertical cell merge
								$style .= ($Trow + $Tmerge[$Trow][$Tcol] - 1 == $Trows) ? " border-bottom".$Tstile['bottom'] : " border-bottom".$Tstile['insideH'];
							} else {  // set the bottom border of a table cell is not a vertical cell merge
								$style .= ($Trow == $Trows) ? " border-bottom".$Tstile['bottom'] : " border-bottom".$Tstile['insideH'];
							}
						}
						if (isset($ts['colspan'])){  // if a table cell is a horizontal cell merge, determine number of additional cells that make up the merge
							$TCoffset = $TCoffset + $ts['colspan'] - 1;
						}
						if (isset($ts['right'])){  // set the right border of the table cell
							$style .= " border-right".$ts['right'];
						} else{
							$style .= ($Tcol + $TCoffset == $TCcount) ? " border-right".$Tstile['right'] : " border-right".$Tstile['insideV'];
						}


						
						if ($Tmerge[$Trow][$Tcol] > 0){
							if ($this->Tcss == 'Y'){
								if ($ts['cellwtype'] == 'pct'){
									$cellwc = "width:".floor($ts['cellwidth'] / 50)."%; ";
								} else if ($ts['cellwtype'] == 'dxa'){
									$cellwc = "width:".round($ts['cellwidth'] / $Twidth *100,2)."%; ";
									$cellwc = '';
								} else {
									$cellwc = '';
								}
								if ($Tmerge[$Trow][$Tcol] == 1){
									if (isset($ts['colspan'])){
										$table .= "<td colspan='".$ts['colspan']."' style='".$cellwc.$style;
									} else {
										$table .= "<td style='".$cellwc.$style;
									}
								} else {
									if (isset($ts['colspan'])){
										$table .= "<td rowspan='".$Tmerge[$Trow][$Tcol]."' colspan='".$ts['colspan']."' style='".$cellwc.$style;
									} else {
										$table .= "<td rowspan='".$Tmerge[$Trow][$Tcol]."' style='".$cellwc.$style;
									}
								}
							} else {
								if ($ts['cellwtype'] == 'pct'){
									$cellwc = "width:".floor($ts['cellwidth'] / 50)."%; ";
								} else if ($ts['cellwtype'] == 'dxa'){
									$cellwc = "width:".round($ts['cellwidth'] / $Twidth *100,2)."%; ";
									$cellwc = '';
								} else {
									$cellwc = '';
								}
								if ($Tmerge[$Trow][$Tcol] == 1){
									if (isset($ts['colspan'])){
										$table .= "<td colspan='".$ts['colspan']."' style='".$cellwc.$style;
									} else {
										$table .= "<td style='".$cellwc.$style;
									}
								} else {
									if (isset($ts['colspan'])){
										$table .= "<td rowspan='".$Tmerge[$Trow][$Tcol]."' colspan='".$ts['colspan']."' style='".$cellwc.$style;
									} else {
										$table .= "<td rowspan='".$Tmerge[$Trow][$Tcol]."' style='".$cellwc.$style;
									}
								}
							}
							$table .= $tc['align']."'>".$tc['cell']."</td>";
						}
						if (isset($ts['colspan'])){
							$Tcol = $Tcol + $ts['colspan'];
						} else {
							$Tcol++;
						}
					}
				}
				$table .= "</tr>";
				$Trow++;
			}
		}

		$table .= "</tbody></table></div>";
		return $table;
	}






	/**
	 * PROCESS THE TABLE CELL STYLE
	 *  
	 * @param string $content - The XML node content
	 * @return - The HTML code of the styling of a table cell
	 */
	private function processTableStyle($content)
	{

		$tc = new XMLReader;
		$tc->xml($content);
		$style = array();

		while ($tc->read()) {
			if ($tc->name === "w:tcBorders") {
				$tc2 = new SimpleXMLElement($tc->readOuterXML());
				foreach ($tc2->children('w',true) as $ch) {
					if (in_array($ch->getName(), ['left','top','bottom','right']) ) {
						$line = '';
						$line = $this->convertLine($ch['val']);
						if ($ch['color'] == 'auto'){
							$tbc = '000000';
						} else {
							$tbc = $ch['color'];
						}
						$zlinT = $ch['sz']/4;
						if ($zlinT >0 AND $zlinT <1){
							$zlinT = 1;
						}
						$Tname = $ch->getName();
						if ($line == "double"){
							$zlinT = $zlinT * 3;
						}
						$style[$Tname] = ":".$zlinT."px ".$line." #".$tbc.";";
					}
				}
				$tc->next();
			}
			if ($tc->name === "w:gridSpan") {
				$style['colspan'] = $tc->getAttribute("w:val");
			}
			if ($tc->name === "w:tcW") {
				$style['cellwidth'] = $tc->getAttribute("w:w");
				$style['cellwtype'] = $tc->getAttribute("w:type");
			}

		}
		return $style;
	}

	/**
	 * PROCESS the BORDER LINE STYLE
	 *  
	 * @param string $in - The XML border line style
	 * @return - The HTML code of the border styling
	 */
	private function convertLine($in)
	{
		if (in_array($in, ['dotted']))
			return "dashed";

		if (in_array($in, ['dotDash','dotdotDash','dotted','dashDotStroked','dashed','dashSmallGap']))
			return "dashed";
		
		if (in_array($in, ['double','triple','threeDEmboss','threeDEngrave','thick']))
			return "double";

		if (in_array($in, ['nil','none']))
			return "none";

		return "solid";
	}

	/**
	 * PROCESS THE TABLE ROW
	 *  
	 * @param string $content - The XML node content
	 * @param String $Tstyle - The name of the table style
	 * @return Array - The HTML code of the table row
	 */
	private function processTableRow($content,$Tstyle)
	{
		$tc = new XMLReader;
		$tc->xml($content);
		$ct = array();
		$count = 0;
		$valign = $halign = $ct['cell'] = $colours = $BackCol = $TextCol = '';
		$Cpos = $Cwidth = 0;
		$ac = 'N';
		while ($tc->read()) {
			$ztpp = '';
			$ztp = '';
			$text = '';
			if ($tc->nodeType == XMLREADER::ELEMENT && $tc->name === 'mc:AlternateContent') {
				$ac = 'Y';
			}
			if ($tc->nodeType == XMLREADER::END_ELEMENT && $tc->name === 'mc:AlternateContent') {
				$ac = 'N';
			}
			if ($tc->nodeType == XMLREADER::ELEMENT && $tc->name === "w:p" AND $ac == 'N') {  // get cell text and its formatting
				$paragraph = new XMLReader;
				$p = $tc->readOuterXML();
				$paragraph->xml($p);
				$ct['cell'] .= $this->getParagraph($paragraph,$Tstyle);
			}
			if ($tc->name === "w:jc") {  // cell text horizontal alignment
				switch($tc->getAttribute("w:val")) {
					case "left":
						$halign =  " text-align: left;";
						break;
					case "center":
						$halign =  " text-align: center;";
						break;
					case "right":
						$halign =  " text-align: right;";
						break;
					case "both":
						$halign =  " text-align: justify;";
						break;
				}
			}
			if ($tc->name === "w:tcW") {  // cell width
				$Cwidth = $tc->getAttribute("w:w");
			}
			if ($tc->name === "w:tab") {  // cell text position
				$Cpos = $tc->getAttribute("w:pos");
			}
			if ($tc->name === "w:vAlign") {  // cell text vertical alignment
				switch($tc->getAttribute("w:val")) {
					case "top":
						$valign =  " vertical-align: top;";
						break;
					case "center":
						$valign =  " vertical-align: center;";
						break;
					case "bottom":
						$valign =  " vertical-align: bottom;";
						break;
				}
			}
			if ($tc->name === "w:shd" AND $tc->getAttribute("w:fill")) {  // cell background color
				$BackCol = $tc->getAttribute("w:fill");
				if ($BackCol == 'auto'){
					$BackCol = 'FFFFFF';
				}
			}
			if ($tc->name === "w:shd" AND $tc->getAttribute("w:color")) {  // cell background color
				$TextCol = $tc->getAttribute("w:color");
			}
			if ($BackCol <> '' OR $TextCol <> ''){
				if ($TextCol == ''){
					$red = hexdec(substr($BackCol,0,2));
					$green = hexdec(substr($BackCol,2,2));
					$blue = hexdec(substr($BackCol,4,2));
					$color = (($red * 0.299) + ($green * 0.587) + ($blue * 0.114) > 186) ?  '000000' : 'FFFFFF';
				} else {
					if ($BackCol == ''){
						$BackCol = 'FFFFFF';
					}
					$color = $TextCol;
				}
				$colours = "background-color: #".$BackCol."; color: #".$color.";";
			}

			
		}

		if ($valign == ''){
			$valign =  " vertical-align: top;";
		}
		if ($halign == ''){
			if ($Cwidth > 0){
				if ($Cpos / $Cwidth < .3){
					$halign =  " text-align: left;";
				} else if ($Cpos / $Cwidth < .7){
					$halign =  " text-align: center;";
				} else {
					$halign =  " text-align: right;";
				}
			}
		}
		$ct['align'] = $halign.$valign.$colours;
		if ($ct['cell'] == ''){
			$ct['cell'] = "&nbsp;";			
		}

		return $ct;
	}
	
	
// ------------------- END OF TABLE PROCESSING ------------------------



	/**
	 * READS THE GIVEN DOCX FILE INTO HTML FORMAT
	 *  
	 * @param String $filename - The DOCX file name
	 * @param String $imgcss - The image and table option parameters
	 * @return String - With HTML code of the DOCX file
	 */
	public function readDocument($filename,$imgcss)
	{
		if (!file_exists($filename)){
			exit("Cannot find file : ".$filename." ");
		}
		$this->file = $filename; // makes the filename available throughout the class
		$OptLen = Strlen($imgcss);
		$this->Icss = substr($imgcss,0,1); // A 'Y' enables images to be styled by external CSS and an 'O' inhibits the display of images
		if ($OptLen > 1){
			$this->Tcss = substr($imgcss,1,1); //A 'Y' makes tables to be 100% width
		} else {
			$this->Tcss = 'N';
		}
		if ($OptLen > 2){
			$this->Hcss = substr($imgcss,2,1); //Puts an full HTML header on the resultant html
		} else {
			$this->Hcss = 'N';
		}
		if ($OptLen > 3){
			$Hfoot = substr($imgcss,3,1); //Includes the header and footer (D = Default, F = First page, E = Even, N = None)
		} else {
			$Hfoot = 'D';
		}
		$this->readZipPart(); // Makes the document and relationships file available throughout the class
		$this->findstyles(); // Makes the style parameters from the styles XML file available throughout the class
		$anchor = array();
		$this->anchor = $anchor;
		
		$reader = new XMLReader();
		$reader->XML($this->doc_xml->saveXML());
		$text = "<div style='position:fixed; bottom:50vh; right:10px; border:2px solid black; padding:2px; min-width:3%; text-align:center; background-color:#eeeeee';><a href='#top'>Top</a></div>"; // Provide a link to go to the top of the page
		$text .= "<div style='margin:10px;'>"; // Provide a small margin around the html output
		$text .= $this->headers($Hfoot); // Gets the headers
		while ($reader->read()) {
		// look for new paragraphs or table
			$paragraph = new XMLReader;
			$p = $reader->readOuterXML();
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name === 'w:tbl') { // finds and gets tables
				$paragraph->xml($p);
				$text .= $this->checkTableFormating($paragraph);
				$reader->next();
			}
			else if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name === 'w:p') {
				// finds and gets paragraphs	
				$paragraph->xml($p); // set up new instance of XMLReader for parsing paragraph independantly	
				$text .= $this->getParagraph($paragraph,'');
				$reader->next();
			}
		}
		$Foot = $this->footnotes(); // Get any Footnotes in the document
		if (isset($Foot[1])) {
			$text .= "<br>&nbsp;";
			$text .= "<hr><p style='margin-top:6px;margin-bottom:6px;'><b>FOOTNOTES</b></p>";
			$Fcount = 1;
			while (isset($Foot[$Fcount])){
				$text .= "<p style='padding-left:50px;text-indent:-50px;margin-top:6px;margin-bottom:6px;'><sup><a id='FN".$Fcount."' href='#FN".$Fcount."R'>[".$Fcount."]</a></sup>&nbsp;&nbsp;&nbsp;".$Foot[$Fcount]."</p>";
				++$Fcount;
			}
		}
		
		$Endn = $this->endnotes(); //Get any Endnotes in the document
		if (isset($Endn[1])) {
			$text .= "<br>&nbsp;";
			$text .= "<hr><p style='margin-top:6px;margin-bottom:6px;'><b>ENDNOTES</b></p>";
			$Fcount = 1;
			while (isset($Endn[$Fcount])){
				$text .= "<p style='padding-left:50px;text-indent:-50px; margin-top:6px;margin-bottom:6px;'><sup><a id='EN".$Fcount."' href='#EN".$Fcount."R'>[".$this->numberToRoman($Fcount)."]</a></sup>&nbsp;&nbsp;&nbsp;".$Endn[$Fcount]."</p>";
				++$Fcount;
			}
		}
		$text .= "<br>&nbsp;";
		$text .= $this->footers($Hfoot); // Gets the footers
		
		$text .= "</div>";
		$reader->close();
		if($this->debug) {  // if in DEBUG mode, display the generated HTML text of the DOCX document
			echo "<div style='width:100%;'>";
			echo mb_convert_encoding($text, $this->encoding);
			echo "</div>";
		}
		if ($this->Maths == 'Y'){ // add in the Mathjax script if required
//			$Mtext = "<script>\n MathJax = {  loader: {load: ['[tex]/mathtools']},\n tex: {packages: {'[+]': ['mathtools']}}, };\n </script>\n <script type=\"text/javascript\" id=\"MathJax-script\" async src=\"https://cdn.jsdelivr.net/npm/mathjax@4/tex-mml-chtml.js\">\n </script>\n";
			$text = $Mtext."\n".$text;
		}
		if ($this->Hcss == 'Y'){
			$Htext = "<!DOCTYPE html>\n <html lang=\"en\">\n <head>\n <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\n <LINK REL=\"STYLESHEET\" TYPE=\"text/css\" HREF=\"/word-htm.css?id=<?= time() ?>\" media=\"screen\">\n </head>\n\n <body>\n ";
			$text = $Htext.$text."\n </body>\n";
		}
		return mb_convert_encoding($text, $this->encoding); // Output the generated HTML text of the DOCX document
	}
}






