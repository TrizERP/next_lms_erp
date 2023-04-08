   <?php  
   if(isset($_POST['firstname']) &&  
     isset($_POST['lastname'])) {  
  echo "Hey {$_POST['firstname']} {$_POST['q']}, 
    you rock!\n(repsonse to your POST request)";  
  }  
if(isset($_GET['firstname']) &&  
      isset($_GET['lastname'])) {  
     echo "Hey {$_GET['firstname']} {$_GET['lastname']}, 
      you rock!\(response to your GET request)";  
}  
echo "<input type=text name=why value=$_POST[q] />";
  ?>  
