<?php
// biblio/record detail
// output the buffer
ob_start(); /* <- DONT REMOVE THIS COMMAND */
?>
<table class="border margined" style="width: 99%;" cellpadding="5" cellspacing="0">
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Title'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{title}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Edition'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{edition}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Call Number'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{call_number}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('ISBN/ISSN'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{isbn_issn}</td>
</tr>
<tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Author(s)'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{authors}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Subject(s)'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{subjects}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Classification'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{classification}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Series Title'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{series_title}</td>
</tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('GMD'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{gmd_name}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Language'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{language_name}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Publisher'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{publisher_name}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Publishing Year'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{publish_year}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Publishing Place'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{publish_place}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Collation'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{collation}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Abstract/Notes'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{notes}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Specific Detail Info'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{spec_detail_info}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Image'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{image}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('File Attachment'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{file_att}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Availability'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{availability}</td>
</tr>
<!-- insert custom field here
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Custom Field 1'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{customfield1}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print gettext('Custom Field 2'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{customfield2}</td>
</tr>
-->
<tr>
<td class="tblHead" style="width: 20%;" valign="top">&nbsp;</td>
<td class="tblContent" style="width: 80%;" valign="top"><a href="javascript: history.back();"><?php print gettext('Back To Previous'); ?></a></td>
</tr>
</table>
<?php
// put the buffer to template var
$detail_template = ob_get_clean();
?>
