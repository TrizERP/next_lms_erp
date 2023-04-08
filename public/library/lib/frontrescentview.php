<?php
echo "<link href=\"js/jsor-jcarousel-7bb2e0a/style.css\" rel=\"stylesheet\" type=\"text/css\" />";
echo "<script type=\"text/javascript\" src=\"js/jsor-jcarousel-7bb2e0a/lib/jquery.jcarousel.min.js\"></script>";
echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"js/jsor-jcarousel-7bb2e0a/skins/tango/skin.css\" />";
echo "<script type=\"text/javascript\" src=\"js/jquery.fancybox-1.3.4/fancybox/jquery.mousewheel-3.0.4.pack.js\"></script>";
echo "<script type=\"text/javascript\" src=\"js/jquery.fancybox-1.3.4/fancybox/jquery.fancybox-1.3.4.pack.js\"></script>";
echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"js/jquery.fancybox-1.3.4/fancybox/jquery.fancybox-1.3.4.css\" media=\"screen\" />";
?>
<script type="text/javascript">
jQuery(document).ready(function()
{    
    jQuery('#mycarousel').jcarousel();
            
});
</script>
<script type="text/javascript">
		$(document).ready(function() {	
                    
			$("a.example1").fancybox();

			$("a#example2").fancybox({
				'overlayShow'	: false,
				'transitionIn'	: 'elastic',
				'transitionOut'	: 'elastic'
			});

			$("a#example3").fancybox({
				'transitionIn'	: 'none',
				'transitionOut'	: 'none'	
			});

			$("a[rel=example_group]").fancybox({
				'transitionIn'		: 'none',
				'transitionOut'		: 'none',
				'titlePosition' 	: 'over',
				'titleFormat'		: function(title, currentArray, currentIndex, currentOpts) {
					return '<span id="fancybox-title-over">Image ' + (currentIndex + 1) + ' / ' + currentArray.length + (title.length ? ' &nbsp; ' + title : '') + '</span>';
				}
			});
			
			$("#various1").fancybox({
				'titlePosition'		: 'inside',
				'transitionIn'		: 'none',
				'transitionOut'		: 'none'
			});

			$("#various2").fancybox();

			$(".various3").fancybox({
				'width'				: '85%',
				'height'			: '85%',
				'autoScale'			: false,
				'transitionIn'		: 'none',
				'transitionOut'		: 'none',
				'type'				: 'iframe'
			});

			$("#various4").fancybox({
				'padding'			: 0,
				'autoScale'			: false,
				'transitionIn'		: 'none',
				'transitionOut'		: 'none'
			});
		});
	</script>  
    <?php 
    
 if(!isset($_REQUEST['p'] ) || $_REQUEST['p']=='' || $_REQUEST['keywords']==''  )
 {  
     
    $qry = "select biblio.image,biblio.title from biblio biblio INNER JOIN biblio_view b2 ON biblio.biblio_id = b2.biblio_id and b2.member_id='".$_SESSION['DUSER_ID']."' order by b2.last_update DESC LIMIT 10";    
    
    $slider_book = $dbs->query($qry);    

    if ($slider_book->num_rows>0)
    {        
    ?>                          
        <center>
            <div id="wrap">             
            <ul id="mycarousel" class="jcarousel-skin-tango">
                <?php   
                if ($slider_book->num_rows>0)
                {                                      
                        while ($data = $slider_book->fetch_assoc())
                        {    
                           if($data['image']!='' || file_exists($data['image']))
                           {
                             echo "<li> <a class=\"example1\" href='images/docs/$data[image]' ><img  src='images/docs/$data[image]' width=\"80\" height=\"70\" alt=\"$data[title]\" title=\"$data[title]\" /></a><br>";                                                                               
                              echo "<u><a class='example1' href='index.php?p=show_detail&id=$data[biblio_id]'>$data[title]</a></u></li>";
                             //   echo "<li> <a class=\"various3\" href=\"http://www.yahoo.com\" ><img  src=\"$path/images/docs/$data[image]\" width=\"75\" height=\"75\" alt=\"$data[title]\" title=\"$data[title]\" /></a></li>";                                                                                                           
                           }
                           else if($data['image']=='' || !file_exists($data['image']))
                           { 
                             echo "<li> <a class=\"example1\" href='images/docs/imagesnotavailable2.jpg' ><img  src='images/docs/imagesnotavailable2.jpg' width=\"80\" height=\"70\" alt=\"$data[title]\" title=\"$data[title]\" /></a><br>"; 
                             echo "<u><a class='example1' href='index.php?p=show_detail&id=$data[biblio_id]'>$data[title]</a></u></li>";
                           }
                         
                        }
                }                
//                if ($slider_book_vertual->num_rows>0)
//                {
//                    echo "<li> <img  src='lib/images/virtual.png' width=\"100\" height=\"100\" alt=\"Virtual Books\" title=\"Virtual Books\" /></li>";                                                                                
//                         while ($data1 = $slider_book_vertual->fetch_assoc())
//                         {   
//                             if($data1[image]=='')                                               
//                                $image_view='lib/images/imagesnotavailable.jpg';                            
//                             else                                 
//                                $image_view=='images/docs/'.$data1[image];
//                             
//                           echo "<li> <a class='various3' href=\"http://www.yahoo.com\" ><img  src=\"$image_view\" width=\"100\" height=\"100\" alt=\"$data1[title]\" title=\"$data1[title]\" /></a> </li>";                                                             
//                         //echo "<li><a class=\"various3\" href=\"http://www.yahoo.com\">Iframe</a></li>";   
//                         
//                         }
//                }
    }
                ?>        
            </ul>
            </div>
            
       </center>
        <?php } ?>