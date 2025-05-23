<?php
try {
    $db = new PDO("mysql:host=127.0.0.1;dbname=yolo", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $users = $db->query("SELECT * FROM user")->fetchAll();
    $error = "";

    // Ajout d'utilisateur
    if (isset($_POST["pseudo"])) {
        if (!empty($_POST["pseudo"]) && !empty($_POST["password"]) && !empty($_POST["email"])) {
            $pseudo = htmlspecialchars($_POST["pseudo"]);
            $email = htmlspecialchars($_POST["email"]);
            $password = password_hash($_POST["password"], PASSWORD_DEFAULT);

            // Gestion de l'avatar
            $avatarFileName = null;
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['avatar']['tmp_name'];
                $originalName = basename($_FILES['avatar']['name']);
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($ext, $allowed)) {
                    $avatarFileName = uniqid('avatar_') . '.' . $ext;
                    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads');
                    move_uploaded_file($tmpName, __DIR__ . '/uploads/' . $avatarFileName);
                }
            }

            $db->beginTransaction();
            $createNewUser = $db->prepare('INSERT INTO user (pseudo, password, email, avatar) VALUES (:pseudo, :password, :email, :avatar)');
            $createNewUser->bindParam(":pseudo", $pseudo);
            $createNewUser->bindParam(":password", $password);
            $createNewUser->bindParam(":email", $email);
            $createNewUser->bindParam(":avatar", $avatarFileName);
            $createNewUser->execute();
            $db->commit();
            header("Refresh:0");
        } else {
            $error = "Veuillez remplir tous les champs";
        }
    }

    // Suppression d'utilisateur
    if (isset($_POST['del']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];

        // Supprimer l'avatar du serveur
        $stmt = $db->prepare("SELECT avatar FROM user WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $user = $stmt->fetch();
        if ($user && !empty($user['avatar'])) {
            $avatarPath = __DIR__ . '/uploads/' . $user['avatar'];
            if (file_exists($avatarPath)) unlink($avatarPath);
        }

        $db->beginTransaction();
        $deleteUser = $db->prepare("DELETE FROM user WHERE id = :id");
        $deleteUser->bindParam(':id', $id);
        $deleteUser->execute();
        $db->commit();
        header("Refresh:0");
    }

    // Modification d'utilisateur
    if (isset($_POST['edit']) && isset($_POST['id'])) {
        if (!empty($_POST["editPseudo"]) && !empty($_POST["editEmail"])) {
            $id = (int)htmlspecialchars($_POST['id']);
            $pseudo = htmlspecialchars($_POST["editPseudo"]);
            $email = htmlspecialchars($_POST["editEmail"]);

            $updatePassword = "";
            $params = [":pseudo" => $pseudo, ":email" => $email, ":id" => $id];

            if (!empty($_POST["editPassword"])) {
                $password = password_hash($_POST["editPassword"], PASSWORD_DEFAULT);
                $updatePassword = ", password = :password";
                $params[":password"] = $password;
            }

            // Gestion de l'avatar
            $updateAvatar = "";
            if (isset($_FILES['editAvatar']) && $_FILES['editAvatar']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['editAvatar']['tmp_name'];
                $originalName = basename($_FILES['editAvatar']['name']);
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($ext, $allowed)) {
                    // Supprimer l'ancien avatar si présent
                    $stmt = $db->prepare("SELECT avatar FROM user WHERE id = :id");
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    $user = $stmt->fetch();
                    if ($user && !empty($user['avatar'])) {
                        $avatarPath = __DIR__ . '/uploads/' . $user['avatar'];
                        if (file_exists($avatarPath)) unlink($avatarPath);
                    }
                    $avatarFileName = uniqid('avatar_') . '.' . $ext;
                    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads');
                    move_uploaded_file($tmpName, __DIR__ . '/uploads/' . $avatarFileName);
                    $updateAvatar = ", avatar = :avatar";
                    $params[":avatar"] = $avatarFileName;
                }
            }

            $db->beginTransaction();
            $sql = "UPDATE user SET pseudo = :pseudo, email = :email $updatePassword $updateAvatar WHERE id = :id";
            $editUser = $db->prepare($sql);
            $editUser->execute($params);
            $db->commit();
            header("Refresh:0");
        } else {
            $error = "Veuillez ne pas laisser de champ vide";
        }
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo $e->getMessage();
}
