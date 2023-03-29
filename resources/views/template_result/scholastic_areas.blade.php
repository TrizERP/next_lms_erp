
<table class="report-card" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
<tbody>
    <tr>
        <td colspan="3">
            <table class="aca-year" style="border-collapse:collapse; border:1px solid; font-size:10px !important;" width="100%" cellspacing="0" cellpadding="0" border="1">
                <tbody>
                    <tr>   
                        <th class="main-th" align="left">&nbsp;</th>   
                        <th  style="text-align: center;" colspan="<?php echo count($all_data['exam']) + 1; ?>" class="main-th"><?php echo $all_data['term'] . " (" . $all_data['total_mark'] . " Marks)"; ?></th>
                    </tr>
                    <tr>   
                        <th align="left">Subjects</th>  
                        <?php
                        foreach ($all_data['exam'] as $temp_id => $exam_data) {
                            ?>
                            <th style="text-align: center;"><?php echo $exam_data['exam']; ?><br>(<?php echo $exam_data['mark']; ?>)</th>
                            <?php
                        }
                        ?>
                        <th style="text-align: center;">Grade</th>
                    </tr>
                    <?php
                    foreach ($all_data['mark'] as $subject => $subject_data) {
                        ?>
                        <tr>   
                            <td><?php echo $subject; ?></td>   
                            <?php foreach ($subject_data as $exam_name => $obtain_point) { ?>
                                <td align="center">
                                    <?php if($obtain_point == '0')
                                            echo '-';
                                        else
                                            echo $obtain_point; 
                                    ?></td>   
                            <?php } ?>
                        </tr>
                        <?php
                    }
                    ?>

                    <tr>
                        <td colspan="<?php echo count($all_data['exam']); ?>"><b>Percentage</b></td>
                        <td align="center"><b><?php echo round($all_data['per'],2); ?>%</b></td>
                        <td align="center"><b><?php echo $all_data['final_grade']; ?></b></td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
</tbody>
</table>