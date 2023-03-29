

  <div style='display:flex;'>
                                                                <?php
                                                                $count = 0;
                                                                if(isset($all_data['co_scholastic_area']))
                                                                {
                                                                    foreach ($all_data['co_scholastic_area'] as $co_area => $arr) {
                                                                        foreach ($arr as $parent => $child_arr) {
                                                                            $count = $count + 1;
                                                                            if($count % 2 == 0)
                                                                            {
                                                                                $margin = "margin-left:2.5%;";
                                                                            }
                                                                            else
                                                                            {
                                                                                $margin = "margin-right:2.5%;";
                                                                            }
                                                                            echo "<div style='display:flex;width:100%;$margin'>";
                                                                            ?>
                                                                            <table class = "aca-year" style = "width: 100%;border-collapse:collapse; border:1px solid;" cellspacing = "0" cellpadding = "0" border = "1">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <th colspan = "2" width = "15%" align = "center"><b><?php echo $parent; ?></b></th>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th width="15%" align="center"><b>Optional Subject</b></th>
                                                                                        <th width="15%" align="center"><b>Grade</b></th>
                                                                                    </tr>
                                                                                    <?php
                                                                                    foreach ($child_arr as $subject => $obtain_grade) {
                                                                                        ?>
                                                                                        <tr>
                                                                                            <td><?php echo $subject; ?></td>
                                                                                            <td align="center"><?php echo $obtain_grade; ?></td>
                                                                                        </tr>

                                                                                    <?php } ?>
                                                                                </tbody>
                                                                            </table>
                                                                            <?php
                                                                            echo "</div>";

                                                                            if ($count % 2 == 0) {
                                                                                echo "</div>";
                                                                                echo "<div class='p-t-10' style='display:flex;'>";
                                                                            }
                                                                        }
                                                                        echo "</div>";
                                                                    }
                                                                }
                                                                ?>