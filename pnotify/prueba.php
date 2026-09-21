<!DOCTYPE html>
<html>

<head>
    <link data-require="bootstrap-css@*" data-semver="3.3.1" rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css" />
    <script data-require="jquery@*" data-semver="2.1.3" src="https://code.jquery.com/jquery-2.1.3.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pnotify/2.0.0/pnotify.all.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pnotify/2.0.0/pnotify.all.js"></script>
</head>

<body>
    <!-- <button onclick="new PNotify({
    title: 'Yayyy you clicked me',
    text: 'Haha, made you click'
});">Click Me!</button> -->
<?php
    if(isset($_POST['foo']) && $_POST['foo'] != "") {
        // $query = 'WRITE_TO_DB_QUERY';
        // $result = mysqli_query($query);
        $result=0;
        if($result==0) {
            // notice how I'm just writing regular JS to the page
            echo "
                <script type='text/javascript'>
                    $(document).ready(function(e) {
                        notifyUser('success');
                    });
                </script>";
        } else {
            echo "
                <script type='text/javascript'>
                    $(document).ready(function(e) {
                        notifyUser('failure');
                    });
                </script>";
        }
    }
?>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
    <input type="text" name="foo" />
    <input type="submit" value="Submit" />
</form>
<script type="text/javascript">
    function notifyUser(message) {
        if(message == "success") {
            new PNotify({
                title: 'Popup Title',
                text: 'Your form as been submitted',
                type: 'info'
            });
        } else {
            new PNotify({
                title: 'Popup Title',
                text: 'Whops, you messed up'
            }); 
        }
    }
</script>
</body>

</html>