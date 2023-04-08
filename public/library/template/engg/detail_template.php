<?php
// biblio/record detail
// output the buffer
ob_start(); /* <- DONT REMOVE THIS COMMAND */
error_reporting(0);

?>
<table class="border margined" style="width: 99%;" cellpadding="5" cellspacing="0">
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Title'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{title}</td>
</tr>
<?php if($_REQUEST['gmd_code']!='Virtual-Resources'){?>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Edition'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{edition}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Call Number'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{call_number}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('ISBN/ISSN'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{isbn_issn}</td>
</tr>
<tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Author(s)'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{authors}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Subject(s)'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{subjects}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Classification'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{classification}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Series Title'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{series_title}</td>
</tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('GMD'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{gmd_name}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Language'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{language_name}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Publisher'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{publisher_name}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Publishing Year'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{publish_year}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Publishing Place'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{publish_place}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Collation'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{collation}</td>
</tr>
<?php }?>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Abstract'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{abstract}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Notes'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{notes}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Specific Detail Info'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{spec_detail_info}</td>
</tr>
<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Image'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{image}</td>
</tr>

<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('File Attachment'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{file_att}</td>
</tr>

<tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Connect'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{labels}</td>
</tr>
<?php if(!$_REQUEST['gmd_code']){?>
<!-- <tr>
<td class="tblHead" style="width: 20%;" valign="top"><?php print __('Availability'); ?></td>
<td class="tblContent" style="width: 80%;" valign="top">{availability}</td>
</tr> -->
<?php }?>
<tr>
<!--<td class="tblHead" style="width: 20%;" valign="top">&nbsp;</td>
<td class="tblContent" style="width: 80%;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript: history.back();" ><?php print __('Back To Previousss'); ?></a></td>-->

<!--<td class="tblContent"><td><a href="index.php??title=&author=&subject=&isbn=&gmd=0&colltype=0&location=0&search=Search"><center><font-size=24>Back To Previous</font></center></a></td></td>--><!-- added by iresh on 4-3-2011 -->
</tr>
</table>
<?php
// put the buffer to template var
$detail_template = ob_get_clean();
?>
