
<?php 
if(count($all_data['grade_range']) > 0)
{
    foreach ($all_data['grade_range'] as $mark_range => $arr) {
        ?>
        <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="100%" cellspacing="0" cellpadding="0" border="1">            
            <tbody>
                <?php                                                                        
                foreach ($arr as $heading => $grd_data) {
                    ?>
                    <tr>                
                        <th align="center" width="200px"><b><?php echo $heading; ?></b></th>
                        <?php foreach ($grd_data as $id => $range) { ?>
                            <td align="center"><?php echo $range; ?></td>
                        <?php } ?>

                    </tr>     
                    <?php
                }
                ?>
            </tbody>
        </table>
        <?php
    }
}
?>
