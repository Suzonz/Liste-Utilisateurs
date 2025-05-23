<?php require "db.php"; ?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./assets/css/style.css">
    <title>Tableau utilisateurs</title>
</head>

<body>
    <header>
        <h1>Gestion des utilisateurs</h1>
    </header>
    <main>
        <div class="form-container">
            <h2>Ajouter un nouvel utilisateur</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="pseudo" placeholder="Pseudo" value="<?= htmlspecialchars($_POST["pseudo"] ?? '') ?>" />
                <input type="email" name="email" placeholder="Votre email" value="<?= htmlspecialchars($_POST["email"] ?? '') ?>" />
                <input type="password" name="password" placeholder="Mot de passe" />
                <input type="file" name="avatar" accept="image/*" />
                <button name="add" type="submit">Inscription</button>
            </form>
        </div>
        <div class="user-list">
            <h2>Liste des utilisateurs</h2>
            <table>
                <tr>
                    <th class="border">Avatar</th>
                    <th class="border">Pseudo</th>
                    <th class="border">Email</th>
                    <th class="border">Action</th>
                </tr>
                <?php foreach ($users as $entry): ?>
                    <tr>
                        <td class="border avatar-cell">
                            <div class="avatar-container">
                                <?php if (!empty($entry['avatar'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($entry['avatar']) ?>" alt="avatar" class="avatar">
                                <?php else: ?>
                                    <span style="color:#aaa;">Aucun</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="border"><?= htmlspecialchars($entry['pseudo'] ?? '') ?></td>
                        <td class="border"><?= htmlspecialchars($entry['email'] ?? '') ?></td>
                        <td>
                            <form class="action-button" method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($entry['id'] ?? '') ?>">
                                <div class="btn-container">
                                    <button name="openModalEdit" class="mini-button" type="submit">
                                        <img src="./assets/image/edit.svg.svg" alt="modifier" width="20px">
                                    </button>
                                    <button name="del" class="mini-button" type="submit">
                                        <img src="./assets/image/trash.svg.svg" alt="supprimer" width="20px">
                                    </button>
                                </div>
                            </form>
                            <?php if (isset($_POST['openModalEdit'], $_POST['id']) && $_POST['id'] == ($entry['id'] ?? null)): ?>
                                <div class="overlay">
                                    <div class="modal">
                                        <h2>Modification</h2>
                                        <form method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($entry['id'] ?? '') ?>">
                                            <input type="text" name="editPseudo" value="<?= htmlspecialchars($entry['pseudo'] ?? '') ?>">
                                            <input type="email" name="editEmail" value="<?= htmlspecialchars($entry['email'] ?? '') ?>">
                                            <input type="password" name="editPassword" placeholder="Nouveau mot de passe (laisser vide pour ne pas changer)">
                                            <input type="file" name="editAvatar" accept="image/*" />
                                            <div class="modal-buttons">
                                                <button name="edit" type="submit">Modifier</button>
                                                <button name="closeModal" type="submit">Fermer</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <p style="color:red;"><?= htmlspecialchars($error ?? '') ?></p>
    </main>
    <footer>
    </footer>
</body>

</html>