<?php

/*
   This file is part of the Windows Compiled HTML Help
   Manual Generator of the PHP Documentation project.

   The logic in this file comes from an extract
   of phpweb/include/shared-manual.inc and
   phpweb/include/layout.inc (clean_note()).
   From time to time, it may be needed to update
   the code, as the manual notes system changes.

   This file is consistent with:
       layout.inc        - 1.66
       shared-manual.inc - 1.125

   This file also includes code to generate a project
   file for the notes.

   Grab the notes file (all compressed) from:
   http://ANY_MIRROR_SITE.php.net/backend/notes/all.bz2
*/

// Check dependencies, die if error found
if (!$USE_NOTES) { die("ERROR: User note generator called"); }

// Merge all possible language manuals. Only the available
// manual[s] will be active, while viewing
$lang_chms = '';
foreach ($LANGUAGES as $code) {
    $lang_chms .= "php_manual_$code.chm\n";
}

// We need a list of files for an IE6 workaround
$NOTE_FILE_LIST = array();

// Start writing the notes project file
$nproject = fopen("$NOTES_TARGET/php_manual_notes.hhp", "w");
fputs($nproject,
"[OPTIONS]
Compatibility=1.1 or later
Compiled file=php_manual_notes.chm
Default topic=_index.html
Display compile progress=No
Full-text search=Yes
Language=0x0809 English (UNITED KINGDOM)
Title=User Notes

[MERGE FILES]
$lang_chms

[FILES]
");

// Walk through all files in HTML_DIR directory
$handle = opendir($HTML_SRC);
while (false !== ($filename = readdir($handle))) {
    // Only process html files generated by XSL sheets
    if (strpos($filename, ".html") && (strpos($filename, "_") !== 0)) {

        // Get title (used for HTML page title)
        $content = join("", file("$HTML_SRC/$filename"));
        preg_match("!<title>([^<]+)</title>!Us", $content, $match);

        // Get user notes, or false on failure
        $user_notes = manualUserNotes($match[1], $filename);

        // If there are no user notes for this page,
        // no file will be generated, but if we generate
        // a file, we add it to project file too
        if ($user_notes != FALSE) {
            $WITH_NOTES++;
            $notes = fopen("$NOTES_TARGET/$filename", "w");
            fwrite($notes, $user_notes);
            fclose($notes);
            fwrite($nproject, $filename . "\n");
			$NOTE_FILE_LIST[] = $filename;
            echo "> $WITH_NOTES\r";
        } else {
            $WITHOUT_NOTES++;
        }
    }
}
closedir($handle);

// Copy note supplemental files, and add to file list
copy("suppfiles/notes/_index.html", "$NOTES_TARGET/_index.html");
fwrite($nproject, "_index.html\n");

copy("suppfiles/notes/_style.css", "$NOTES_TARGET/_style.css");
fwrite($nproject, "_style.css\n");

// RAQ : Wednesday, 16 March 2005 01:54 pm : Allow all note pages to have a global JavaScript file.
copy('suppfiles/notes/_notes_script.js', "$NOTES_TARGET/_notes_script.js");
fwrite($nproject, "_notes_script.js\n");

// Write out a list of files to work around an IE6 bug
$jsfile = fopen("$NOTES_TARGET/_filelist.js", "w");
fwrite($jsfile, "note_file_list = ' " . join(" ", $NOTE_FILE_LIST) . " ';\n\n");
fwrite($jsfile, "if (note_file_list.indexOf(' ' + chmfile_page + ' ') != -1) { notesIframe(); }");
fclose($jsfile);
fwrite($nproject, "_filelist.js\n");

// Close ready project file
fclose($nproject);

// Make entry HTML fragment for a user note
function makeEntry($date, $name, $blurb)
{
    // Begin user notes header
    $entryhtml = "<div><p class=\"unheader\">\n";

    // Get email/name of the user note writer
    $name = htmlspecialchars($name);
    if ($name && $name != "php-general@lists.php.net" && $name != "user@example.com") {
        if (ereg("(.+)@(.+)\.(.+)", $name)) {
            $entryhtml .= "<a href=\"mailto:$name\" class=\"useremail\"><span>$name</span></a>";
        } else {
            $entryhtml .= "<b><span>$name</span></b>";
        }
    }
    // Append date
    $entryhtml .= " (<span>" . date("d-M-Y h:i", $date) . "</span>)</p>\n";
    // Append user note text, cleared
    $entryhtml .= "<p class=\"untext\"><tt><span>" . clean_note($blurb) . "</span></tt></p></div>\n";
    // Return with entry HTML fragment
    return $entryhtml;
}

// Get user notes for this particular page, returns an array with notes
function manualGetUserNotes($id)
{
    global $NOTES_SRC, $NOTE_NUM;
    $notes = array();
    $hash = substr(md5($id),0,16);
    $notes_file = "$NOTES_SRC/$hash[0]/$hash";
    if ($fp = @fopen($notes_file,"r")) {
        while (!feof($fp)) {
            $line = chop(fgets($fp,8096));
            if ($line == "") continue;
            list($id,$sect,$rate,$ts,$user,$note) = explode("|",$line);
            $notes[] = array(
                "id"    => $id,
                "sect"  => $sect,
                "rate"  => $rate,
                "xwhen" => $ts,
                "user"  => $user,
                "note"  => base64_decode($note)
            );
        }
        fclose($fp);
    }
    $NOTE_NUM += count($notes);
    return $notes;
}

// Get the HTML file for a user note page, or false on failure
function manualUserNotes($title, $id)
{
    // Don't want .html at the end of the ids
    if (substr($id,-5) == '.html') { $id = substr($id,0,-5); }

    // Get user notes
    $notes = manualGetUserNotes($id);

    // If we have no notes, do not produce useless HTML
    if (count($notes) == 0) {
        return FALSE;
    }

    // start HTML output
    // RAQ : Wednesday, 16 March 2005 01:54 pm : Allow all note pages to have a global JavaScript file and use new notesLoading() function for body's onLoad.
    $notehtml = <<<END_OF_MULTI
<html>
<head>
  <title>N: $title</title>
  <link rel="stylesheet" href="_style.css">
  <script language="JavaScript1.2" src="_notes_script.js"></script>
</head>
<body onLoad="if(parent.nbuff) {parent.displayNotes();} else {loadWithNotes();}">

<h3>User contributed notes:</h3>
END_OF_MULTI;

    // Go through manual notes, and make entries
    foreach ($notes as $note) {
        $notehtml .= makeEntry($note['xwhen'], $note['user'], $note['note'], $note['id']);
    }

    // Be good guys, and end HTML code here
    $notehtml .= "\n</body></html>";

    // Return with manual notes for this page
    return $notehtml;
}

// Clean note to get right display
function clean_note($text) {

    // Do not allow people to fool the system with bogus HTML
    $text = htmlspecialchars(trim($text));

    // turn urls into links
    $text = preg_replace("/((mailto|http|ftp|nntp|news):.+?)(&gt;|\\s|\\)|\"|\\.\\s|$)/","<a href=\"\\1\">\\1</a>\\3",$text);

    // this 'fixing' code will go away eventually
    $fixes = array('<br>','<p>','</p>');
    reset($fixes);
    while (list(,$f)=each($fixes)) {
        $text=str_replace(htmlspecialchars($f), $f, $text);
        $text=str_replace(htmlspecialchars(strtoupper($f)), $f, $text);
    }

    // Allow only one <br> (drop out long empty sections)
    $text = preg_replace("!(<br>\\s*)+!", "<br>", $text);

    // wordwrap is not applicable here, because (it is buggy and)
    // we need to make text break as window resized

    // Convert new lines in notes to <br> / <br />
    $text = nl2br(preg_replace("![\\n\\r]+!", "\n", $text));

    // Get files to smaller size (bad hack :)
    $text = str_replace("<br />", "<br>", $text);

    // We do not use <pre> but would like to see indentation
    $text = str_replace("  ", " &nbsp;", $text);

    // Paras cannot be used here, replace with <br><br>
    $text = str_replace("<p>", "<br><br>", $text);

    // Drop out end of paras
    $text = str_replace("</p>", "", $text);

    return $text;
}

?>
