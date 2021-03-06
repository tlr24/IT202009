<?php require_once(__DIR__ . "/partials/nav.php"); ?>
<title>Profile</title>
<h1>Profile</h1>
<?php

$db = getDB();

$public_profile = false;
// get id of user profile
if (isset($_GET["id"])) {
    $id = $_GET["id"];
    $stmt = $db->prepare("SELECT id, username, first_name, last_name, CAST(created AS DATE) as created, visibility from Users where id = :id");
    $stmt->execute([":id" => $id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_info) {
        if ($user_info["visibility"] == 1) {
            $public_profile = true;
        }
        else {
            // if the profile is private but the user is the one logged in, they can see their own profile page
            if (get_user_id() == $user_info["id"]) {
                $public_profile = true;
            }
            // if the user is private, others can't see the profile page
            else {
                flash("Cannot access page, user account is private");
                die(header("Location: home.php"));
            }
        }
    }
}
else {
    if (!is_logged_in()) {
        //this will redirect to login and kill the rest of this script (prevent it from executing)
        flash("You must be logged in to access this page");
        die(header("Location: login.php"));
    }
}

//save data if we submitted the form
if (isset($_POST["saved"])) {
    $isValid = true;
    //check if our email changed
    $newEmail = get_email();
    if (get_email() != $_POST["email"]) {
        //TODO we'll need to check if the email is available
        $email = $_POST["email"];
        $stmt = $db->prepare("SELECT COUNT(1) as InUse from Users where email = :email");
        $stmt->execute([":email" => $email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $inUse = 1;//default it to a failure scenario
        if ($result && isset($result["InUse"])) {
            try {
                $inUse = intval($result["InUse"]);
            }
            catch (Exception $e) {

            }
        }
        if ($inUse > 0) {
            flash("Email is already in use");
            //for now we can just stop the rest of the update
            $isValid = false;
        }
        else {
            $newEmail = $email;
        }
    }
    $newUsername = get_username();
    if (get_username() != $_POST["username"]) {
        $username = $_POST["username"];
        $stmt = $db->prepare("SELECT COUNT(1) as InUse from Users where username = :username");
        $stmt->execute([":username" => $username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $inUse = 1;//default it to a failure scenario
        if ($result && isset($result["InUse"])) {
            try {
                $inUse = intval($result["InUse"]);
            }
            catch (Exception $e) {

            }
        }
        if ($inUse > 0) {
            flash("Username is already in use");
            //for now we can just stop the rest of the update
            $isValid = false;
        }
        else {
            $newUsername = $username;
        }
    }
    $newFirstName = $_POST["first_name"];
    if (strlen($newFirstName) == 0) {
        flash("Please enter a first name");
        $isValid = false;
    }
    $newLastName = $_POST["last_name"];
    if (strlen($newLastName) == 0) {
        flash("Please enter a last name");
        $isValid = false;
    }
    $visible = $_POST["visible"];
    if ($visible != "1" && $visible != "0") {
        flash("Please enter public or private account visibility");
        $isValid = false;
    }
    if ($isValid) {
        $stmt = $db->prepare("UPDATE Users set email = :email, username= :username, first_name = :firstname, last_name = :lastname, visibility = :visible where id = :id");
        $r = $stmt->execute([":email" => $newEmail, ":username" => $newUsername, ":firstname" => $newFirstName, ":lastname" => $newLastName, ":visible" => $visible, ":id" => get_user_id()]);
        if ($r) {
            flash("Updated profile");
        }
        else {
            flash("Error updating profile");
        }
        //password is optional, so check if it's even set
        //if so, then check if it's a valid reset request
        if (!empty($_POST["currPassword"]) && !empty($_POST["password"]) && !empty($_POST["confirm"])) {
            $stmt1 = $db->prepare("SELECT password from Users WHERE (email = :email)");
            $params = array(":email" => $newEmail);
            $r1 = $stmt1->execute($params);
            $result1 = $stmt1->fetch(PDO::FETCH_ASSOC);
            $password_hash_from_db = $result1["password"];
            unset($result1["password"]);
            if (password_verify($_POST["currPassword"], $password_hash_from_db)) {
              if ($_POST["password"] == $_POST["confirm"]) {
                $password = $_POST["password"];
                $hash = password_hash($password, PASSWORD_BCRYPT);
                //this one we'll do separate
                $stmt = $db->prepare("UPDATE Users set password = :password where id = :id");
                $r = $stmt->execute([":id" => get_user_id(), ":password" => $hash]);
                if ($r) {
                    flash("Reset password");
                }
                else {
                    flash("Error resetting password");
                }
              }
              else {
   	              flash("Passwords don't match");
	            }
            }
            else {
                flash("Invalid current password"); 
            }
            
            
        }
//fetch/select fresh data in case anything changed
        $stmt = $db->prepare("SELECT email, username from Users WHERE id = :id LIMIT 1");
        $stmt->execute([":id" => get_user_id()]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $email = $result["email"];
            $username = $result["username"];
            //let's update our session too
            $_SESSION["user"]["email"] = $email;
            $_SESSION["user"]["username"] = $username;
        }
    }
    else {
        //else for $isValid, though don't need to put anything here since the specific failure will output the message
    }
}


?>

<?php if (!$public_profile): ?>
<form method="POST">
  <h2>Account Info</h2>
  <p>
    <label for="email">Email</label>
    <input type="email" name="email" value="<?php safer_echo(get_email()); ?>"/>
  </p>
  <p>
    <label for="username">Username</label>
    <input type="text" maxlength="60" name="username" value="<?php safer_echo(get_username()); ?>"/>
  </p>
    <p>
        <label for="first_name">First Name</label>
        <input type="text" maxlength="30" name="first_name" value="<?php echo get_firstname(get_user_id()); ?>"/>
    </p>
    <p>
        <label for="last_name">Last Name</label>
        <input type="text" maxlength="30" name="last_name" value="<?php echo get_lastname(get_user_id()); ?>"/>
    </p>
    <p>
        <input type="radio" name="visible" value="1" <?php echo (get_profile_visibility(get_user_id()) == 1)?"checked='checked'":""?>"/>Public
        <input type="radio" name="visible" value="0" <?php echo (get_profile_visibility(get_user_id()) == 0)?"checked='checked'":""?>"/>Private
    </p>
  <h2>Password Reset</h2>
  <p>
    <label for="opw">Current Password</label>
    <input type="password" name="currPassword"/>
  </p>
  <p>
    <!-- DO NOT PRELOAD PASSWORD-->
    <label for="npw">New Password</label>
    <input type="password" name="password"/>
  </p>
  <p>
    <label for="cpw">Confirm Password</label>
    <input type="password" name="confirm"/>
  </p>
    <input type="submit" name="saved" value="Save Profile"/>
</form>
<?php else: ?>
    <h2>Account Info</h2>
    <p><b>Username: </b><?php safer_echo($user_info["username"]); ?></p>
    <p><b>Name: </b><?php safer_echo($user_info["first_name"]); ?> <?php safer_echo($user_info["last_name"]); ?></p>
    <p><b>Account Created: </b><?php safer_echo($user_info["created"]); ?></p>
<?php endif; ?>

<?php require(__DIR__ . "/partials/flash.php");
