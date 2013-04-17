<?php

include "vars.php";

if ( stristr( $_SERVER['SCRIPT_NAME'], basename( __FILE__ ) ) ) { exit( 'No direct script access allowed' ); }

if(empty($myvar)) {
   die("No direct script access allowed");
}

function dieLdap($message, $conn) {
   mail('NOTIFY', 'verify.php LDAP died.', $message);
   ldap_unbind($conn);
   header("Location: http://www/");
   die($message);
}

function dieMsql($message) {
   mail('NOTIFY', 'verify.php MySQL died.', $message);
   mysql_close();
   header("Location: http://www./");
   die($message);
}

function mailAdmin($message) {
   mail('NOTIFY', 'Duplicate UIDs in use.', $message);
}

function mailUser($user, $email) {
   $to      = $email; // Send email to our user
   $subject = 'Account Activation'; // Give the email a subject
   $message = '

Hello '.$user.',

Your email address is:
'.$user.'@somesite.com

If you experience any issues with registration, please send e-mail to 'NOTIFY'.

Regards.

'; // Our message above including the link

   $headers = "From:noreply@somesite.com"; // Set from headers
   mail($to, $subject, $message, $headers); // Send our email
}

$arrErrors = array();
if (count($arrErrors) == 0) {

   // Make sure we can connect to mysql and ldap.
   mysql_connect(DB_HOST, DB_USER, DB_PASSWORD) or dieMsql(mysql_error()); // Connect to database server(localhost) with username and password.
   $conn = ldap_connect(LDAP_HOST) or dieLdap("Could not connect to server, Error is: " . ldap_error($conn), $conn);
   // fetch data from mysql database
   mysql_select_db(DB_NAME) or dieMsql(mysql_error()); // Select registration database.

   if(isset($_GET['e']) && !empty($_GET['e']) AND isset($_GET['h']) && !empty($_GET['h'])){  
      // Verify data  
      $email = mysql_escape_string($_GET['e']); // Set email variable  
      $hv = mysql_escape_string($_GET['h']); // Set hash variable  
  
      $search = mysql_query("SELECT cn, sn, username, password, email, hash, active FROM users WHERE email='".$email."' AND hash='".$hv."' AND active='0'") or dieMsql(mysql_error());  
      while ($row = mysql_fetch_assoc($search)) {
         // prepare data
         $info["cn"] = rtrim($row['cn']);
         $info["sn"] = rtrim($row['sn']);
         $info["uid"] = rtrim($row['username']);
         $info["mail"] = rtrim($row['email']);
         $info["userPassword"] = "{SHA}" . $row['password'];
         $info["objectClass"][0] = "inetOrgPerson";
      }
      $match = mysql_num_rows($search); 

      // Let's check the e-mail address against stopforumspam.org
      $name = $info["uid"];
      $mail = $info["mail"];
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
         header("Location: /contribute/thankyou/");
         die("Spammer");
      } elseif ($conf >= 50) {
         header("Location: /contribute/thankyou/");
         die("Spammer");
      }
  
      if($match > 0){  
         // We have a match, activate the account  
         mysql_query("UPDATE users SET active='1' WHERE email='".$email."' AND hash='".$hv."' AND active='0'") or dieMsql(mysql_error());  
        
         // Specifiy which version of LDAP to use
         ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
         
         // bind to the LDAP server
         $r = ldap_bind($conn, LDAP_USER, LDAP_PASS) or dieLdap("Could not bind to server. Error is: " . ldap_error($conn), $conn);

         // Need to find next available UID
         $dn = 'LDAP_USER_BASE';
         $filter="uidNumber=*";
         $returnFilter = array("uidNumber");
         $sr = ldap_search($conn, $dn, $filter, $returnFilter);
         $uidInfo = ldap_get_entries($conn, $sr);
         $nextUid = 0; 
         // This finds last max uid.  New code finds first unused one based
         // on sorted array, starts with 40005
         usort($uidInfo, function ($elem1, $elem2) {
            return strcmp($elem1['uidnumber'][0], $elem2['uidnumber'][0]);
         });

         for($i=2; $i<=$uidInfo[0]; $i++) {
           if($uidInfo[$i]["uidnumber"][0] == $uidInfo[$i]["uidnumber"][0]-1) {
              mailAdmin("UID number " . $uidInfo[$i-1]["uidnumber"][0] . " is duplicated.  Please login and fix.");
              continue;
           }
           if($uidInfo[$i-1]["uidnumber"][0] != $uidInfo[$i]["uidnumber"][0]-1) {
              $nextUid = $uidInfo[$i-1]["uidnumber"][0]+1;
              break;
           }
         }
         if (empty($nextUid)) {
            $nextUid = $uidInfo[$i-1]["uidnumber"][0]+1;
         }

         // Let's double check here that we're not re-using an id.
         // $sr = ldap_search($conn, $dn, uidNumber=$nextUid, array("uidN:");
         // $uidInfo = ldap_get_entries($conn, $sr);
         // while (!empty($uidInfo[0]["uid"])):
         //     $nextUid = $nextUid + 1;
         //     $sr = ldap_search($conn, $dn, uidNumber=$nextUid, array("uidN:");
         //     $uidInfo = ldap_get_entries($conn, $sr);
         // endwhile;
         //

         // prepare DN for new entry
         $dn = "uid=" . $info["uid"] . "," . LDAP_USER_BASE;

         if(preg_match("/(somesite.om$)/", $info["mail"])) {
            $homeDir = "/path/to/home/" . $info["uid"];
            $info["objectClass"][1] = "shadowAccount";
            $info["objectClass"][2] = "posixAccount";
            $info["homeDirectory"] = $homeDir;
            $info["uidNumber"] = $nextUid;
            $info["gidNumber"] = "100";
            $info["loginShell"] = "/bin/bash";
            $info["shadowExpire"] = "-1";
            $info["shadowFlag"] = "0";
            $info["shadowWarning"] = "7";
            $info["shadowMin"] = "8";
            $info["shadowMax"] = "999999";
            $info["shadowLastChange"] = "10877";
            $info["title" ] = "Full Account";
            if (!is_dir($homeDir)){
              shell_exec("./createHome" . " " . $homeDir . " " .  $nextUid); 
            }
            $result = ldap_add($conn, $dn, $info);

            if ($result == FALSE) {
                if (ldap_errno($conn) == '68') {
                    header("Location: /contribute/userexists/");
                    die();
                }
                dieLdap("Ldap add command failed for user: " . $info["uid"] . "\nEmail: " . $info["mail"] . "\ncn: " . $info["cn"] . "\nsn: " . $info["sn"] . ".\nLDAP error number: " . ldap_errno($conn) . ", LDAP error message: " . ldap_error($conn), $conn);
            }
            mailUser($info["uid"], $info["mail"]);
         } else {
            $result = ldap_add($conn, $dn, $info);

            if ($result == FALSE) {
                if (ldap_errno($conn) == '68') {
                    header("Location: /contribute/userexists/");
                    die();
                }
                dieLdap("Ldap add command failed for user: " . $info["uid"] . "\nEmail: " . $info["mail"] . "\ncn: " . $info["cn"] . "\nsn: " . $info["sn"] . ".\nLDAP error number: " . ldap_errno($conn) . ", LDAP error message: " . ldap_error($conn), $conn);
            }
         }

        ldap_unbind($conn);
        header("Location: /contribute/thankyou/");
      }else{  
         // No match -> invalid url or account has already been activated.  
         header("Location: /contribute/activated/");
      }  
  
   }else{  
      // Invalid approach  
      header("Location: /contribute/invalidapproach/");
   }  

}
mysql_close();
die();
?>
