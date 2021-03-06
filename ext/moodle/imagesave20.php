<?php
/*
 * Copyright (c) 2009-2014, Mihai Sucan
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 * 
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 
 * 3. Neither the name of the copyright holder nor the names of its contributors
 *    may be used to endorse or promote products derived from this software without
 *    specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * $URL: http://code.google.com/p/paintweb $
 * $Date: 2014-01-28 12:38:36 $
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
//
// The image is saved only if the user is logged-in, and only if the file type 
// is known (that is, PNG or JPEG). The image is saved using the new File API in 
// the user file drafts area.

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

