# This is an improved class to read a Microsoft Word DOCX file and output it in HTML format for the web.

## DESCRIPTION

This improved Microsoft Word DOCX to HTML class will recognise nearly all the formatting, themes, images, tables, text-boxes etc. in the original Word DOCX document. It will now also display any Mathematical Equations in the document either standalone or inline with normal text. The only significant exception is Wordart and tabs. Tabs are very difficult to replicate in HTLM due to a web page page width being very flexible and different sizes of screens. The resultant HTML should look very much like the original.

For update notes detailing changes up to the latest version of 2.1.14 see below.

NOTE - Needs at least php 5 and will run on up to (at least) php 8.5.

NOTE:- It will not read the older 'DOC' Word format. In this case it will give an error message saying that it is a 'non zip file'.

I have a website featuring all my php scripts where you can also test this script with one of your own Word documents - https://phpscripts.meccanoindex.co.uk. You can also contact me through my website.

FEATURES

 1. It will use the correct font and font size (assuming that a common font is being used).

 2. Text formating - Bold, Underlining (various styles/colours), Italic, Single & Double Strikethrough, Superscript and Subscript are all replicated, along with text alignment : Left, Centre, Right, Justified. Also indented and hanging text

 3. It will recognise and display horizontal lines across the page - dotted, dashed, double and thicker single lines.

 4. It will display multi-level lists with the correct alpha-numeric numbering as per the original word document.

 5. It will now recognise paragraphs numbered with the Word paragraph numbering function as well as the list numbering function.

 6. It will display tables with merged cells correctly along with border and cell colours etc. 

 7. In the default mode, tables are replicated as near as possible to the original DOCX word document formatting and relative size. E.g. if a table takes half the width of the pages then it will take up half the width of the screen. They will also be left, centre or right aligned as per Word. Also allows for text to be parallel with a table - either left or right side. However an option is provided for the tables to take up 100% of screen width (to allow for better display in mobile devices etc.). A 'DIV' is also placed around all tables with the class name of 'tab' to allow for external common CCS formatting if required.

 8. Both footnote and endnote references are located in the correct place in the text. All the actual footnotes and endnotes are located at the end of the text. Links are provided to jump from a reference to actual note and then back again.

 9. The bookmarks in a 'Table of Contents' or similar provide a link to the correct section of the document as per the original Word document. A return link is also provided.

 10. Href hyperlink targets follow what is set in Word - enables one to select whether to open a link in the same tab/window, or a new one.

 11. As many Word documents are quite long a link is provided to jump back to the top of the document. Located at the middle of the right side of the screen.

 12. Will recognise and display most images contained in the original DOCX word document. Including transparancy in PNG files.

 13. In the default mode, images are formatted and sized very similarly to the original DOCX word document, which is fine for desktop computers. However an option is provided to allow for external CSS formatting to be used instead (e.g. to allow for better display in mobile devices etc.). In this mode each image is given a unique CSS class name - 'Wimg1' for the first image. 'Wimg2' for the second image, etc. to enable formatting of each image as desired. There is also an option to omit images from the resultant HTML if this is desired.

 14. Will now recognise when images are cropped and display the correct cropped image. Will also recognise when images have been rotated (0-360deg) or flipped in Word and display the image correctly.

 15. By default images are saved into the 'images' directory, which is automatically created if it does not exist. An option is provided to enable the name of this directory to be changed if desired.

 16. The browser is now prevented from using cached images. Avoids caching using old images instead of the new ones when updating the images in a Word document.

 17. Text boxes are recognised along with any rotation they may have. Note 180deg rotation is the only practical method of having upside-down text in Word. I have tried to replicate approximately, whether they are near the left, centre or right of the page. Note that due to a combination of how Word puts them in the DOCX file and the vagaries of trying to accurately position things in html, the position of these text boxes is only approximate, unless they are constrained in a table cell.

 18. It will now recognise symbols from most of the symbol character sets used in Word (Wingdings, Wingdings 2, Wingdings 3, Webdings, Symbol, Zapf Dingbats). These in the main are not commonly available on the web. However most of the characters or equivalents are available in the Unicode character set so these are used instead. Available when using php 7.2 and above. Please note that not all browsers can display the full Unicode character set.

 19. Will now recognise nearly all Word Mathematical Equations, both standalone and inline with text. It does this using the online version of Mathjax (so internet acces is required for this). Note that Mathjax does not support Double Square Brackets, so any occurences of this are replaced by the Double Pipe.

 20. It will now also display headers and footers including any images in them. The default is to show them - when there is more than one set of headers and footers in the document it shows:-
	a) Second page onwards when the first page is different.
	b) Odd numbered pages when the document has different ones for odd and even pages.
	You can select whether to show the default headers/footers, or one of the others if there is more than one. You can also opt not to display them.

 21. The resultant html code is designed to be used either as is, or (after saving) included in another html file. However an option is provided to add a html header, so that after saving it can be used as a standalone file (along with any images that it contains).

If anyone finds any problems or has sugestions for enhancements, please contact me on timothy.edwards1@btinternet.com 

# BASIC USAGE
```
require_once('wordphp.php');
$rt = new WordPHP(false);
$text = $rt->readDocument('FILENAME','N');
echo $text;
```

# DETAILED USAGE

## Increase php memory limit - 
Optional - Only needed when there are high resolution images in the Word document and PHP fatal errors due to using up all the available memory are produced
```
ini_set('memory_limit', '512M'); OR ini_set('memory_limit', '1G');
```

## Include the class in your php script
```
require_once('wordphp.php');
```

## debug mode 
Will display the various zipped XML files in the DOCX file which are used by the class for the document being converted and will also display the resultant HTML.
```
$rt = new WordPHP(true);
```

## without debug (Normal Use)
```
$rt = new WordPHP(false); or $rt = new WordPHP();
```

## Set output encoding (Default is UTF-8)
You can alter the encoding of the resultant HTML - eg. 'ISO-8859-1', 'windows-1252', etc. Although note that many special chacters and symbols may not then display correctly so the default should be used whenever practical.
```
$rt = new WordPHP(false, 'ISO-8859-1');
```

## Change directory for images (Default is 'images')
Will change the directory used for any images in the document.
```
$rt = new WordPHP(false, null, 'dir_name');
```

## Read docx file and return the html code - Default mode
See below for various options
```
$text = $rt->readDocument('FILENAME','N');
```

## Display the html code on screen
```
echo $text;
```

##  Save the html code to a file (if required)
```
$myfile = fopen("newfile.php", "w") or die("Unable to open file!");

fwrite($myfile, $text)
```

# OPTIONS

## Read docx file and return the html code - Option 1a
Images can be formatted using external CSS
```
$text = $rt->readDocument('FILENAME','Y');
```

## Read docx file and return the html code - Option 1b
Images will be omitted
```
$text = $rt->readDocument('FILENAME','O');
```

## Read docx file and return the html code - Option 2
Tables will be formatted to 100% of screen width
```
$text = $rt->readDocument('FILENAME','NY');
```

## Read docx file and return the html code - Option 3
Resultant HTML code will have a standard HTML header added so that when saved, the saved file can be run directly in a browser.
N.B if you wish to use this option in conjunction with option 1a then the CSS file should be named - word-htm.css
```
$text = $rt->readDocument('FILENAME','NNY');
```

## Read docx file and return the html code - Option 4a
Display the headers and footers (or default ones if more than one)
```
$text = $rt->readDocument('FILENAME','N');  OR $text = $rt->readDocument('FILENAME','NNND');
```

## Read docx file and return the html code - Option 4b
Display the headers and footers from page 1 (where different to rest of dccument)
```
$text = $rt->readDocument('FILENAME','NNNF');
```

## Read docx file and return the html code - Option 4c
Display the headers and footers from even pages (where odd and even pages are different)
```
$text = $rt->readDocument('FILENAME','NNNF');
```

## Read docx file and return the html code - Option 4d
Do NOT display the headers and footers
```
$text = $rt->readDocument('FILENAME','NNNN');
```

## UPDATE NOTES

Version 2.1.15 - Now using Mathjax version 4, so it will now display Surface and Volume Integral symbols. Also several bug fixes to clear undefined variable warning messages in PHP 8.2 and above. It has been tested up to PHP 8.5

Version 2.1.14 - It will now display horizontal lines correctly. A DIV has been placed around tables. Hyperlinks now follow what is set in Word. Prevented the browser caching of images which can cause problems if the images are updated in a document. It will recognise images that have been rotated or flipped in Word and display them. It will now recognise Text Boxes and any rotation they may have. Headers and Footers are also now included including any images in them. PNG images can now have transparent backgrounds. It will now correctly replicate the relative width of a table no matter how it is done - '%' or 'cm'. Whether by whole table and/or individual columns. e.g. if a table takes up 50% of the available page width, it will take up 50% of the screen width. It will also now recognise whether the table is left, centre or right justified and display accordingly. It will also allow for having text alongside a table - left or right side. Unfortunately at the moment I can't replicate have a centre table with text either side, so in this case it just reverts to putting the table on the left and the text on the right.

Version 2.1.13 - It will now recognise many of the various underlining styles in Word. It will duplicate the following styles:- Single, Double, Dotted, Dashed and Wavy together with their heavy/thick versions and coloured underlining. It will also now recognise 'double strikethrough. In addition a few bugs have been cleared.

Version 2.1.12 - Elements with subscripts etc. are now allowed in matrices. Also standalone mathematical equations can now be left or right aligned to the page or element (eg table cell) as per the Word document instead of always in the centre.

Version 2.1.11 - Enhancements and bug fixes - It will now recognise and display Mathematical Equations (using Mathjax). Also clears a bug in list/paragraph numbering and a bug with merged cells in tables. An option is now provided to omit any images from the resultant html code if this is required. Also an option to include a html header to the resultant code is provided for when the html code is saved and used as a standalone file.

Version 2.1.10 - It will now recognise when images are cropped and display the correct cropped image. Previously it displayed the full original image.

Version 2.1.9 - Enhancement - It will now recognise symbols from most of the symbol character sets used in Word (Wingdings, Wingdings 2, Wingdings 3, Webdings, Symbol, Zapf Dingbats) when using php 7.2 and above. These character sets in the main are not commonly available on the web/browsers. However most of the characters or equivalents are available in Unicode so these are used instead. Bug fixes:- 1. If the first few characters in a paragraph were bold and/or underlined, then the whole paragraph was. 2. If a blank line contained a single space, then the blank line was ignored.

Version 2.1.8 - Various enhancements and bug fixes. 1. Will now recognise multiple spaces, - 2. Will recognise pdf images. - 3. Will now tell you if the .docx file cannot be found. - 4. Will now recognise checkboxes. - 5. Will now recognise most common list bullets (assuming that UTF-8 is used). - 6. Multiple bugfixes (Table widths and alignments not always correct - Top and bottom margins of text not always displayed correctly - Several other minor fixes).

Version 2.1.7 - Modified to clear a lot of php Warning Messages that appear in some environments

Version 2.1.6 - Will now work on up to (at least) php 8.1

Version 2.1.5 - Various enhancements. 1. Will now operate much faster. Previously, was often several seconds, now will give the output in a fraction of a second. - 2. Will now recognise Drop Capitals. - 3. Will now recognise a Cross-Reference link in the document. - 4. Text formatting now implemented in the Footnotes and endnotes. - 5. Rectified a bug where sometimes hyperlinks were not recognised properly in footnotes and endnotes.

Version 2.1.4 - Clearance of a minor bug.

Version 2.1.3 - Clearance of a few bugs: 1. The endnote references in the text were not in roman numerals as they should be. - 2. In certain circumstances the correct font and font size were not being used. - 3. In certain circumstances a bookmark link did not work.

Version 2.1.2 - Will now recognise higher resolution images (Word deals with these differently to lower resolution images).

Version 2.1.1 - Will now recognise paragraph numbering as well as list numbering.

Version 2.1.0 - Improved method of displaying lists. Can now display footnotes and endnotes and their text references. The bookmarks in a 'Table of Contents' or similar, now link to the appropriate section of the document.
