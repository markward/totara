<?php

    require_once '../../config.php';

?>

// Bind functionality to page on load
$(function() {

    ///
    /// Add related competency dialog
    ///
    (function() {
        var url = '<?php echo $CFG->wwwroot ?>/hierarchy/type/competency/related/';

        mitmsAssignDialog(
            'related',
            url+'find.php?id='+competency_id,
            url+'save.php?id='+competency_id+'&add='
        );
    })();

    /// Assign evidence item dialog
    ///
    (function() {
        var url = '<?php echo $CFG->wwwroot ?>/hierarchy/type/competency/evidenceitem/';

        var handler = new mitmsDialog_handler_assignEvidence();
        handler.baseurl = url;

        mitmsDialogs['evidence'] = new mitmsDialog(
            'evidence',
            'show-evidence-dialog',
            {},
            url+'edit.php?id='+competency_id,
            handler
        );
    })();

});

// Create handler for the assign evidence dialog
mitmsDialog_handler_assignEvidence = function() {
    // Base url
    var baseurl = '';
}

mitmsDialog_handler_assignEvidence.prototype = new mitmsDialog_handler_skeletalTreeview();

mitmsDialog_handler_assignEvidence.prototype._handle_hierarchy_expand = function(id) {
    var url = this.baseurl+'category.php?id='+id;
    this._dialog._request(url, this, '_update_hierarchy', id);
}


mitmsDialog_handler_assignEvidence.prototype._handle_course_click = function(id) {
    // Load course details
    var url = this.baseurl+'course.php?id='+id+'&competency='+competency_id;
    this._dialog._request(url, this, '_display_evidence');
}

/**
 * Display course evidence items
 *
 * @param string    HTML response
 */
mitmsDialog_handler_assignEvidence.prototype._display_evidence = function(response) {

    $('.selected', this._dialog.dialog).html(response);

    var handler = this;

    // Handle add evidence links
    $('.selected a', this._dialog.dialog).click(function(e) {
        e.preventDefault();
        handler._dialog._request($(this).attr('href'), handler, '_update');
    });
}