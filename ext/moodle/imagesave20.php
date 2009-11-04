<?php
/*
 * Copyright (C) 2009 Mihai Şucan
 *
 * This file is part of PaintWeb.
 *
 * PaintWeb is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PaintWeb is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PaintWeb.  If not, see <http://www.gnu.org/licenses/>.
 *
 * $URL: http://code.google.com/p/paintweb $
 * $Date: 2009-10-07 15:54:52 +0300 $
 */

// This script performs asynchronous image save in PaintWeb. This is used by the 
// Moodle extension of PaintWeb, to save image edits. You should not include 
// this script yourself.

// This script only works with Moodle 2.0.


// The Moodle extension (see paintweb/src/extensions/moodle.js) calls this 
// script with several parameters:
//   - url: the URL of the image being edited. '-' is used for images with data 
//   URLs.
//
//   - dataURL: the dataURL generated by the browser. This holds the 
//   base64-encoded content of the image.
//
//   - contextid and draftitemid: both are used when saving the new image file 
//   inside the user_draft file area of Moodle 2.0. If the draftitemid/contextid 
//   values are missing, then new values will be determined.

require_once('../../../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Send the JSON object result to PaintWeb.
 *
 * @param string $url The image URL we are saving/updating.
 * @param string $urlnew The new image URL generated for the saved image.
 * @param boolean $successful Tells if the save operation was successful or not.
 * @param string $errormessage Holds an error message if the save operation 
 * failed.
 */
function paintweb_send_result($url, $urlnew, $successful, $errormessage=null) {
    $output = array(
        'successful'   => $successful,
        'url'          => $url,
        'urlNew'       => $urlnew,
        'errorMessage' => $errormessage
    );

    echo json_encode($output);
    exit;
}

// Files saved by PaintWeb are stored in the draft area.
$filearea = 'user_draft';
$filepath = '/'; // ... in the root folder

// The list of allowed image MIME types associated to file extensions.
$imgallowedtypes = array(
    'image/png'  => 'png',
    'image/jpeg' => 'jpg'
);

$imgurl = optional_param('url', '', PARAM_URL);
if (empty($imgurl)) {
    $imgurl = '-';
}

$imgdataurl = required_param('dataURL', PARAM_RAW);

$draftitemid = optional_param('draftitemid', '', PARAM_INT);
if (empty($draftitemid)) {
    $draftitemid = (int)substr(hexdec(uniqid()), 0, 9) + rand(1, 100);
}

$context = get_context_instance(CONTEXT_USER, $USER->id);
$contextid = optional_param('contextid', SITEID, PARAM_INT);
$imgurlnew = null;

if (empty($contextid)) {
    $contextid = $context->id;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isloggedin() ||
    !repository::check_context($contextid)) {
    paintweb_send_result($imgurl, $imgurlnew, false,
        get_string('moodleServer:permissionDenied', 'paintweb'));
}

if (empty($imgdataurl)) {
    paintweb_send_result($imgurl, $imgurlnew, false,
        get_string('moodleServer:saveEmptyDataUrl', 'paintweb'));
}

// A data URL starts like this:
// data:[<MIME-type>][;charset="<encoding>"][;base64],<data>
// See details at:
// http://en.wikipedia.org/wiki/Data_URI_scheme

$mimetype = 'text/plain';
$base64data = '';

$regex = '/^data:([^;,]+);base64,(.+)$/';

$matches = array();
if (preg_match($regex, $imgdataurl, $matches)) {
    $mimetype   = $matches[1];
    $base64data = $matches[2];
    $imgdataurl = null;
} else {
    paintweb_send_result($imgurl, $imgurlnew, false,
        get_string('moodleServer:malformedDataUrl', 'paintweb'));
}

if (empty($base64data) || !isset($imgallowedtypes[$mimetype])) {
    paintweb_send_result($imgurl, $imgurlnew, false,
        get_string('moodleServer:malformedDataUrl', 'paintweb'));
}

$imgdata = base64_decode($base64data);
$base64data = null;

$filename = 'paintweb_' . sha1($imgdata) . '.' . $imgallowedtypes[$mimetype];

// Save the file using the new File API.

$fs = get_file_storage();
$fbrowser = get_file_browser();

$file_record = new object();
$file_record->contextid = $context->id;
$file_record->filearea  = $filearea;
$file_record->itemid    = $draftitemid;
$file_record->filepath  = $filepath;
$file_record->filename  = $filename;
$file_record->userid    = $USER->id;

try {
    $file = $fs->create_file_from_string($file_record, $imgdata);
} catch (Exception $err) {
    paintweb_send_result($imgurl, $imgurlnew, false, $err->getMessage());
}
$imgdata = null;

$binfo = $fbrowser->get_file_info($context, $file->get_filearea(),
    $file->get_itemid(), $file->get_filepath(), $file->get_filename());

if (empty($binfo)) {
    paintweb_send_result($imgurl, $imgurlnew, false,
        get_string('moodleServer:saveFailed', 'paintweb'));
}

$imgurlnew = $binfo->get_url();

if (empty($imgurlnew)) {
    paintweb_send_result($imgurl, $imgurlnew, false,
        get_string('moodleServer:saveFailed', 'paintweb'));
}

paintweb_send_result($imgurl, $imgurlnew, true);

// vim:set spell spl=en fo=tanqrowcb tw=80 ts=4 sw=4 sts=4 sta et noai nocin fenc=utf-8 ff=unix: 
