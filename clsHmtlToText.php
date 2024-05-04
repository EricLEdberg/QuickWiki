<?php
// ------------------------------------------------------------
// Convert an HTML document to TEXT   (used by Full Text Search when indexing web page)
// Algorithm is not 100% correct but does well enough.
//
// http://www.codeproject.com/Articles/639/Removing-HTML-from-the-text-in-ASP
// https://gist.github.com/gwobcke/1027133
// http://www.barattalo.it/asp/asp-strip-tags-function-equivalent-to-php/
// http://forums.devshed.com/asp-programming-51/regex-remove-ascii-characters-string-395202.html
// https://snipplr.com/view/8404/how-to-strip-html-tags-scripts-and-styles-from-a-web-page
//
// ELE - 9/2016     Wrote original ASP version
// ELE - 4/24       Ported to PHP and added some methods
// ------------------------------------------------------------
function gfHtml2Text($xStr) {
	$i = 0;
    
	if ( is_null($xStr) || (strcmp($xStr,"")==0) ) {
		return $xStr;
  }

    // remove everything between open/close tag including everything between the tags
    // Cannot use this....  It deletes evrything between <html></html> or body, etc...
    // $xPattern    = '/(<.*>.+?)+(<\/.*>)/i';
    // $xStr        = preg_replace($xPattern, '', $xStr);
    

    // Have not verified what this actually removes.  Will have to further validate PHP BODY content
    // ISSUE-1:  when processing an Email that was saved as index.html, it deleted the entire contents of the html-formatted email.
    // Commented out until additional testing can commence.
    $xStr2 = preg_replace(
        array(
          // Remove invisible content
            '@<head[^>]*?>.*?</head>@siu',
            '@<style[^>]*?>.*?</style>@siu',
            '@<script[^>]*?.*?</script>@siu',
            '@<object[^>]*?.*?</object>@siu',
            '@<embed[^>]*?.*?</embed>@siu',
            '@<applet[^>]*?.*?</applet>@siu',
            '@<noframes[^>]*?.*?</noframes>@siu',
            '@<noscript[^>]*?.*?</noscript>@siu',
            '@<noembed[^>]*?.*?</noembed>@siu',
          // Add line breaks before and after blocks
            '@</?((address)|(blockquote)|(center)|(del))@iu',
            '@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu',
            '@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu',
            '@</?((table)|(th)|(td)|(caption))@iu',
            '@</?((form)|(button)|(fieldset)|(legend)|(input))@iu',
            '@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu',
            '@</?((frameset)|(frame)|(iframe))@iu',
        ),
        array(
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
            "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0",
            "\n\$0", "\n\$0",
        ),
        $xStr );


    // Remove images including embedded image data (see .QWContent.php when users paste images into the editor)
    // Must be before strip_tags()
    $xPattern    = '/(<img>.+?)+(<\/img>)/i';
    $xStr        = preg_replace($xPattern, '', $xStr);
    
    // https://www.php.net/manual/en/function.strip-tags.php
    // This leaves all text between the tags
    $xStr        = strip_tags($xStr);
    
	// replace text backslash characters (not rendered)
    $xPattern    = '[\\\\n|\\\\t|\\\\r|\\\\v|\\\\f|\\\\b]';
    $xStr        = preg_replace($xPattern," ",$xStr);

    // Replace HTML special characters.
    $xPattern    = "'&[a-zA-Z]{4};'";
    $xStr        = preg_replace($xPattern," ",$xStr);
    
    // strip some non-ascii characters
    $xPattern    = "/^\x20-\x7E/";
    $xStr        = preg_replace($xPattern," ",$xStr);
    
    // remove other non-ascii characters (this seems to work better that above)
    // https://stackoverflow.com/questions/8781911/remove-non-ascii-characters-from-string
    $xStr = preg_replace('/[[:^print:]]/', '', $xStr);
    
    // Don't know what this does :-(  It was in the original ASP version so just keep it.
    // Possibly remove 1 or more newlines?
    $xPattern    = "[<(.|\n)+?>]";;
    $xStr        = preg_replace($xPattern," ",$xStr);
    
    // Untested - Remove HTML Tags (from php preg_replace manual in the comments section)
    // $string      = preg_replace ('/<[^>]*>/', ' ', $string); 
    $xPattern    = "'/<[^>]*>/'";;
    $xStr        = preg_replace($xPattern," ",$xStr);
    
    // LAST replace 1-or-more spaces with 1 space
    $xPattern    = "[\s{2,}]";
    $xStr        = preg_replace($xPattern," ",$xStr);
	
	return $xStr;
}

/**  Original function added here for reference
 * 
 * https://snipplr.com/view/8404/how-to-strip-html-tags-scripts-and-styles-from-a-web-page
 * 
 * Remove HTML tags, including invisible text such as style and
 * script code, and embedded objects.  Add line breaks around
 * block-level tags to prevent word joining after tag removal.
 */
function strip_html_tags( $text )
{
    $text = preg_replace(
        array(
          // Remove invisible content
            '@<head[^>]*?>.*?</head>@siu',
            '@<style[^>]*?>.*?</style>@siu',
            '@<script[^>]*?.*?</script>@siu',
            '@<object[^>]*?.*?</object>@siu',
            '@<embed[^>]*?.*?</embed>@siu',
            '@<applet[^>]*?.*?</applet>@siu',
            '@<noframes[^>]*?.*?</noframes>@siu',
            '@<noscript[^>]*?.*?</noscript>@siu',
            '@<noembed[^>]*?.*?</noembed>@siu',
          // Add line breaks before and after blocks
            '@</?((address)|(blockquote)|(center)|(del))@iu',
            '@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu',
            '@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu',
            '@</?((table)|(th)|(td)|(caption))@iu',
            '@</?((form)|(button)|(fieldset)|(legend)|(input))@iu',
            '@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu',
            '@</?((frameset)|(frame)|(iframe))@iu',
        ),
        array(
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
            "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0",
            "\n\$0", "\n\$0",
        ),
        $text );
    return strip_tags( $text );
}

// $xTest = gfHtml2Text('<junk myvar=1>ddjafj<img> sdfs<test>fdaasfd\n</test>\b\fthis is</junk> a        test too');
// echo "<li>xTest: ($xTest)</li>";
