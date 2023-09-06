<?php
require '../../../sysconfig.inc.php';
// start the session
//require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';

$page_title = 'Member Loan List';
?>
<script type="text/javascript">
/**
 * Function to change date text to input text
 */
var changeDateForm = function(intLoanID, strDateToChange, strDateElementID)
{
    var dateElement = $(strDateElementID);
    var dateElementVal = dateElement.innerHTML.strip();
    var inputDate = 'input' + strDateElementID;
    var inputDateElement = '<input type="text" value="' + dateElementVal + '" name="' + inputDate + '" id="' + inputDate + '" maxlength="10" size="10" />';
    dateElement.insert({before: inputDateElement}).hide();
    $(inputDate).focus();
    // utility function as an Event listener
    var changeListener = {
        changed: function(event) {
            var inputDate = Event.element(event);
            changeLoanDate(intLoanID, strDateToChange, strDateElementID, inputDate.getValue());
        }
    }
    var evtListener = changeListener.changed.bindAsEventListener(changeListener);
    $(inputDate).observe('blur', evtListener);
}

/**
 * Function to send AJAX request to change loan and due date
 */
var changeLoanDate = function(intLoanID, strDateToChange, strDateElementID, strDate)
{
    var dateData = {newLoanDate: strDate, loanSessionID: intLoanID};
    var dateElement = $(strDateElementID);
    if (strDateToChange == 'due') {
        dateData = {newDueDate: strDate, loanSessionID: intLoanID};
    }
    var ajaxJSON = new Ajax.Request('<?php echo MODULES_WEB_ROOT_DIR.'circulation/loan_date_AJAX_change.php'; ?>',
    {
        method: 'post',
        parameters: dateData,
        onSuccess: function(ajaxTransport) {
                // get AJAX response text
                var respText = ajaxTransport.responseText.strip();
                if (!respText) {
                    return;
                }
                noResult = false;
                // evaluate json respons
                var sessionDate = respText.evalJSON();
                // update date element
                dateElement.update(sessionDate.newDate);
            }
        });
    // remove input date
    $('input' + strDateElementID).remove();
    dateElement.show();
}
</script>
<?php
$js = ob_get_clean();

// start the output buffering
//ob_start();
// check if there is member ID

if (isset($_SESSION['memberID1'])) 
{
    
    $memberID = trim($_SESSION['memberID1']);
    ?>
    <!--item loan form-->
    <div style="padding: 5px; background: #CCCCCC;">
        <form name="itemLoan" id="loanForm" action="circulation_action.php" method="post" style="display: inline;">
            <?php echo gettext('Insert Item Code/Barcode'); ?> :
            <input type="text" id="tempLoanID" name="tempLoanID" onkeyup="return checkspecialcharacterdynamic(this.name);" autofocus/>
            <input type="submit" name="loan" value="<?php echo gettext('Issue'); ?>" class="button" />
        </form>
    </div>
    <script type="text/javascript">jQuery('tempLoanID').focus();</script>
    <!--item loan form end-->
    <?php
    // make a list of temporary loan if there is any
    if (count($_SESSION['temp_loan']) > 0) 
    {
        
        // create table object
        $temp_loan_list = new simbio_table();
        $temp_loan_list->table_attr = "align='center' style='width: 100%;' cellpadding='3' cellspacing='0'";
        $temp_loan_list->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
        $temp_loan_list->highlight_row = true;
        // table header
        $headers = array(gettext('Remove'),  gettext('Item Code'), gettext('Title'), gettext('Loan Date'), gettext('Due Date'));
        $temp_loan_list->setHeader($headers);
        // row number init
        $row = 1;
        foreach ($_SESSION['temp_loan'] as $_loan_ID => $temp_loan_list_d) 
        {
                        // alternate the row color
            $row_class = ($row%2 == 0)?'alterCell':'alterCell2';

            // remove link
            $remove_link = '<a href="circulation_action.php?removeID='.$temp_loan_list_d['item_code'].'" title="Remove this item" class="trashLink">&nbsp;</a>';

            // check if manually changes loan and due date allowed
            if ($sysconf['allow_loan_date_change']) 
            {
                $loan_date = '<a href="#" title="'.gettext('Click To Change Loan Date').'" onclick="changeDateForm(\''.$_loan_ID.'\', \'loan\', \'loanDate'.$row.'\')" id="loanDate'.$row.'">'.$temp_loan_list_d['loan_date'].'</a>';
                $due_date = '<a href="#" title="'.gettext('Click To Change Due Date').'" onclick="changeDateForm(\''.$_loan_ID.'\', \'due\', \'dueDate'.$row.'\')" id="dueDate'.$row.'">'.$temp_loan_list_d['due_date'].'</a>';
            } 
           else
           {
                $loan_date = date("d-m-Y", strtotime($temp_loan_list_d['loan_date']));
                $due_date = date("d-m-Y", strtotime($temp_loan_list_d['due_date']));
            }

            // row colums array
            $fields = array($remove_link, $temp_loan_list_d['item_code'], $temp_loan_list_d['title'], $loan_date, $due_date);

            
            
            // append data to table row
            $temp_loan_list->appendTableRow($fields);
            // set the HTML attributes
            $temp_loan_list->setCellAttr($row, null, "valign='top' class='$row_class'");
            $temp_loan_list->setCellAttr($row, 0, "valign='top' align='center' class='$row_class' style='width: 5%;'");
            $temp_loan_list->setCellAttr($row, 1, "valign='top' class='$row_class' style='width: 10%;'");
            $temp_loan_list->setCellAttr($row, 2, "valign='top' class='$row_class' style='width: 60%;'");

            $row++;
        }

//        echo $temp_loan_list->printTable();
    }

}

$content = ob_get_clean();
require SENAYAN_BASE_DIR.'/admin/admin_template/notemplate_page_tpl.php';
?>
