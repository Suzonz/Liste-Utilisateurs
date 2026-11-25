<?php
// En local, les erreurs sont affichées ; en ligne, mettre APP_DEBUG=0 chez l'hébergeur
$debug = getenv('APP_DEBUG') !== '0';
ini_set('display_errors', $debug ? '1' : '0');

/** Après un ajout, une modification ou une suppression : on recharge la page (sans renvoyer le formulaire). */
function backToList()
{
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/**
 * Enregistre l'avatar envoyé dans uploads/ : seulement une vraie image (jpg, png, gif, webp), 2 Mo maximum.
 * Renvoie [nom du fichier, null], [null, message d'erreur], ou [null, null] si aucun fichier n'a été envoyé.
 */
function saveAvatar($file)
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) {
        return [null, "L'avatar doit faire 2 Mo maximum."];
    }

    $allowed = ['jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP];
    $ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
    $info = @getimagesize($file['tmp_name']);
    // L'extension doit être autorisée, et le contenu du fichier doit être une image du même type
    if (!isset($allowed[$ext]) || !$info || $info[2] !== $allowed[$ext]) {
        return [null, "L'avatar doit être une image JPG, PNG, GIF ou WEBP."];
    }

    $avatarFileName = uniqid('avatar_') . '.' . $ext;
    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads');
    move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $avatarFileName);
    return [$avatarFileName, null];
}

/** Supprime du serveur l'avatar actuel de l'utilisateur. */
function deleteAvatar($db, $id)
{
    $stmt = $db->prepare("SELECT avatar FROM user WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $user = $stmt->fetch();
    if ($user && !empty($user['avatar'])) {
        $avatarPath = __DIR__ . '/uploads/' . basename($user['avatar']);
        if (file_exists($avatarPath)) unlink($avatarPath);
    }
}

try {
    // Connexion : variables d'environnement chez l'hébergeur, réglages locaux (Laragon) sinon
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'yolo';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPassword = getenv('DB_PASSWORD') ?: '';
    $db = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $dbUser, $dbPassword);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $users = $db->query("SELECT * FROM user")->fetchAll();
    $error = "";

    // Ajout d'utilisateur
    // (les textes sont enregistrés tels quels : l'échappement se fait à l'affichage, dans index.php)
    if (isset($_POST["pseudo"])) {
        $pseudo = trim($_POST["pseudo"]);
        $email = trim($_POST["email"] ?? '');
        if (!empty($pseudo) && !empty($_POST["password"]) && !empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "L'adresse email n'est pas valide";
            } else {
                // Gestion de l'avatar
                [$avatarFileName, $error] = saveAvatar($_FILES['avatar'] ?? null);

                if (!$error) {
                    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
                    $db->beginTransaction();
                    $createNewUser = $db->prepare('INSERT INTO user (pseudo, password, email, avatar) VALUES (:pseudo, :password, :email, :avatar)');
                    $createNewUser->bindParam(":pseudo", $pseudo);
                    $createNewUser->bindParam(":password", $password);
                    $createNewUser->bindParam(":email", $email);
                    $createNewUser->bindParam(":avatar", $avatarFileName);
                    $createNewUser->execute();
                    $db->commit();
                    backToList();
                }
            }
        } else {
            $error = "Veuillez remplir tous les champs";
        }
    }

    // Suppression d'utilisateur
    if (isset($_POST['del']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];

        // Supprimer l'avatar du serveur
        deleteAvatar($db, $id);

        $db->beginTransaction();
        $deleteUser = $db->prepare("DELETE FROM user WHERE id = :id");
        $deleteUser->bindParam(':id', $id);
        $deleteUser->execute();
        $db->commit();
        backToList();
    }

    // Modification d'utilisateur
    if (isset($_POST['edit']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $pseudo = trim($_POST["editPseudo"] ?? '');
        $email = trim($_POST["editEmail"] ?? '');
        if (!empty($pseudo) && !empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "L'adresse email n'est pas valide";
            } else {
                $updatePassword = "";
                $params = [":pseudo" => $pseudo, ":email" => $email, ":id" => $id];

                if (!empty($_POST["editPassword"])) {
                    $password = password_hash($_POST["editPassword"], PASSWORD_DEFAULT);
                    $updatePassword = ", password = :password";
                    $params[":password"] = $password;
                }

                // Gestion de l'avatar : le nouveau remplace l'ancien
                $updateAvatar = "";
                $hasNewAvatar = isset($_FILES['editAvatar']) && $_FILES['editAvatar']['error'] !== UPLOAD_ERR_NO_FILE;
                if ($hasNewAvatar) {
                    [$avatarFileName, $error] = saveAvatar($_FILES['editAvatar']);
                    if (!$error) {
                        deleteAvatar($db, $id);
                        $updateAvatar = ", avatar = :avatar";
                        $params[":avatar"] = $avatarFileName;
                    }
                }

                if (!$error) {
                    $db->beginTransaction();
                    $sql = "UPDATE user SET pseudo = :pseudo, email = :email $updatePassword $updateAvatar WHERE id = :id";
                    $editUser = $db->prepare($sql);
                    $editUser->execute($params);
                    $db->commit();
                    backToList();
                }
            }
        } else {
            $error = "Veuillez ne pas laisser de champ vide";
        }
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log($e->getMessage());
    // Le détail de l'erreur n'est montré qu'en local
    $error = $debug ? $e->getMessage() : "Une erreur est survenue, réessayez plus tard.";
    $users = $users ?? [];
}
