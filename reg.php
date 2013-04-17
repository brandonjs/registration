<?php

require_once('vars.php');

if ( stristr( $_SERVER['SCRIPT_NAME'], basename( __FILE__ ) ) ) { exit( 'No direct script access allowed' ); }

if(empty($myvar)) {
   die("No direct script access allowed");
}

function postvars() {
   foreach(func_get_args() as $var) {
      if(!isset($_POST[$var]) || $_POST[$var] === '') return false;
   }
   return true;
}

function dieMail($message, $conn) {
   mail('NOTIFY', 'signup.php died.', $message);
   header("Location: 'WEB_FRONT'");
   die($text);
}

function mailUser($user, $password, $email, $hash) {
   $to      = $email; // Send email to our user
   $subject = 'Account Registration'; // Give the email a subject
   $message = 'Thank you for signing up for an account!

You have registered with the following username: '.$user.'

Before you can login, you will need to validate your e-mail address. Please click the link below to do so.

http://'WEB_FRONT'/registration/verify.php?e='.$email.'&h='.$hash.'

If you believe you recieved this e-mail in error, or did not register with an account on 'WEB_FRONT', please disregard this e-mail.

If you experience any issues with registration, please send e-mail to US.

Thank You.


'; // Our message above including the link

   $headers = "From: noreply"; // Set from headers
   mail($to, $subject, $message, $headers); // Send our email
}

class ac
{
   function __construct ($cn, $sn, $uid, $pw, $mail)
   {
      $this->cn = rtrim($cn);
      $this->sn = rtrim($sn);
      $this->username = rtrim($uid);
      $this->password = $pw;
      $this->email = rtrim($mail);
      // Create registration hash
      // Example output: f4552671f8909587cf485ea990207f3b
      $this->hv = md5( rand(0,1000) );
   }

}

$arrErrors = array();

if(!(postvars('password','passwordConfirm','cn','sn','uid','mail','captcha-493'))) {
   array_push($arrErrors, "postvars returned false");
}

// No numbers in First or Last name allowed.
$sarray = array($_POST['cn'], $_POST['sn']);

//foreach ($sarray as $value) {
//   preg_match("/([0-9]|[a-z].*[A-Z]{2}$| )/", $value);
//   array_push($arrErrors, "Bad CN Value");
//}


if (count($arrErrors) == 0) {

   // Hash and salt the password
   $clearPassword = $_POST['password'];
   $confirmPass = $_POST['passwordConfirm'];
   $userPassword = base64_encode( pack( "H*", sha1( $clearPassword ) ) );
   $info = new ac($_POST['cn'], $_POST['sn'], $_POST['uid'], $userPassword, $_POST['mail']);

   // Let's check the e-mail address against stopforumspam.org
   $name = $info->username;
   $mail = $info->email;
   $url = "http://www.stopforumspam.com/api?username=$name&email=$mail&confidence";
   $xml = simplexml_load_file($url);
   $mailSeen = $xml->appears[0];
   $nameSeen = $xml->appears[1];
   $conf = $xml->confidence;
   if($conf == '' ) {
      $conf = 0;
   }

   // It's a spammer.  Don't deny, just quietly quit.
   if($mailSeen == "yes" && $nameSeen == "yes") {
      header("Location: /contribute/needverify/");
      die("Spammer");
   } elseif ($conf >= 50) {
      header("Location: /contribute/needverify/");
      die("Spammer");
   }

   if ($clearPassword != $confirmPass) {
      header("Location: /contribute/badpassword/");
      die("Bad password");
   }

   // Check for blank entries.
//   while (list($var, $val) = each($info)) {
//      if (empty($val)) {
//         die($var . " is empty for $val: " . $info[$var] . " Not adding to mysql");
//      }
//   }

   // add data to mysql database
   mysql_connect(DB_HOST, DB_USER, DB_PASSWORD) or die(mysql_error()); // Connect to database server(localhost) with username and password.

   mysql_select_db(DB_NAME) or die(mysql_error()); // Select registration database.
   $checkemail = mysql_query("SELECT * FROM users WHERE email='".$info->email."'") or die(mysql_error());
   $checkuname = mysql_query("SELECT * FROM users WHERE username='".$info->username."'") or die(mysql_error());

   if (mysql_num_rows($checkemail)) {
      header("Location: /contribute/emailexists/");
   } elseif (mysql_num_rows($checkuname)) {
      header("Location: /contribute/userexists/");
   } else {
      mysql_query("INSERT INTO users (cn, sn, username, password, email, hash) VALUES(
'". mysql_escape_string($info->cn) ."',
'". mysql_escape_string($info->sn) ."',
'". mysql_escape_string($info->username) ."',
'". mysql_escape_string($info->password) ."',
'". mysql_escape_string($info->email) ."',
'". mysql_escape_string($info->hv) ."') ") or die(mysql_error());

      mailUser($info->username, $clearPassword, $info->email, $info->hv);
      header("Location: /contribute/needverify/");

   }
   $info = null;
   mysql_close();
} else {
   header("Location: /contribute/badvalues/");
   print_r($arrErrors);
   die("Proper values not set");
}
die();
?>
