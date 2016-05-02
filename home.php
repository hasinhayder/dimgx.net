<?php
require_once "functions.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DimgX/Dummy Image X: Replace attachment urls in your WordPress export file using placeholders</title>
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="css/style.css">
</head>
<body>
<h1>DimgX</h1>
<p>DimgX helps you to replace the attachment urls in your WordPress content file with dummy placeholder images.</p>
<form action="" method="post" enctype="multipart/form-data">
    <?php
    $link = "";
    if(isset($_POST['submit'])){
        if(isset($_FILES['content']['name']) && trim($_FILES['content']['name'])!=''){

            $fileName = $_FILES['content']['name'];
            $extension = pathinfo($fileName,PATHINFO_EXTENSION);

            if( $extension!="xml"){
                echo "We don't touch this <b>{$extension}</b> type file. Only <b>xml</b> files are allowed.<br/>";
            }else{
                $fileContent = file_get_contents($_FILES["content"]["tmp_name"]);
                $processedData = processData($fileContent);
                $newFileName=  "xmls/data-dimgx-".ceil(mt_rand(0,10000)).time().".xml";
                file_put_contents($newFileName,$processedData);
                $link = $newFileName;
            }

        }
    }
    ?>

    <?php
    if(!$link){
    ?>
    Upload WordPress Export File (xml): <input type="file" name="content" id="content">
    <br/>
    <input type="submit" name="submit" value="Process" class="btn btn-primary process">
    <?php
    } else {
    ?>
        <a href="<?php echo $link; ?>" download="<?php echo $fileName;?>">Download Processed File</a> | <a href='/'>Upload Another File</a>
        <?php
    }
    ?>
</form>
<script src="//cdn.jsdelivr.net/jquery/2.1.4/jquery.min.js"></script>
<script src="assets/js/bg.js?2"></script>
<script type="text/javascript">
    $(document).ready(function(){
        var index = getRandomInt(0,bgs.length);
        var bg = bgs[index];
        $("body").addClass(bg.type).css("background-image",'url('+ bg.url +')');
    });

    function getRandomInt(min, max) {
        return Math.floor(Math.random() * (max - min)) + min;
    }
</script>
</body>
</html>