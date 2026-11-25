// ============================================================
//  DÉMO EN LIGNE : le travail de db.php, mais dans le navigateur
//  Les utilisateurs sont gardés dans sessionStorage (le stockage de
//  l'onglet) : chaque visiteur a sa propre liste, effacée à la fermeture
//  de l'onglet. Rien n'est envoyé à un serveur.
//  Les mots de passe ne sont pas conservés (la version PHP les chiffre en base).
// ============================================================
const STORAGE_KEY = "liste-utilisateurs-demo";
const MAX_AVATAR_SIZE = 2 * 1024 * 1024; // 2 Mo, comme dans db.php
const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/gif", "image/webp"];
const EMAIL_FORMAT = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Deux exemples pour que le tableau ne soit pas vide au premier passage
const EXAMPLES = [
    { id: 1, pseudo: "Minou", email: "minou@exemple.fr", avatar: null },
    { id: 2, pseudo: "Caramel", email: "caramel@exemple.fr", avatar: null }
];

const usersBody = document.querySelector("#users");
const errorText = document.querySelector("#error");
const addForm = document.querySelector("#add-form");

// ---------- Stockage ----------
function loadUsers() {
    try {
        const saved = sessionStorage.getItem(STORAGE_KEY);
        return saved ? JSON.parse(saved) : EXAMPLES.slice();
    } catch {
        return EXAMPLES.slice();
    }
}

function saveUsers(users) {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(users));
        return true;
    } catch {
        return false; // stockage plein (trop d'avatars) ou indisponible
    }
}

let users = loadUsers();

// ---------- Avatar : vérifié puis réduit à 140 px pour tenir dans le stockage ----------
function readAvatar(file) {
    return new Promise((resolve, reject) => {
        if (!file || !file.size) return resolve(null);
        if (file.size > MAX_AVATAR_SIZE) return reject("L'avatar doit faire 2 Mo maximum.");
        if (!ALLOWED_TYPES.includes(file.type)) return reject("L'avatar doit être une image JPG, PNG, GIF ou WEBP.");

        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            const scale = Math.min(1, 140 / Math.max(img.width, img.height));
            const canvas = document.createElement("canvas");
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            canvas.getContext("2d").drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            resolve(canvas.toDataURL("image/png"));
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject("L'avatar doit être une image JPG, PNG, GIF ou WEBP.");
        };
        img.src = url;
    });
}

// ---------- Affichage (textContent : le HTML tapé s'affiche comme du texte) ----------
function cell(className) {
    const td = document.createElement("td");
    if (className) td.className = className;
    return td;
}

function iconButton(src, alt, onClick) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "mini-button";
    const img = document.createElement("img");
    img.src = src;
    img.alt = alt;
    img.width = 20;
    button.append(img);
    button.addEventListener("click", onClick);
    return button;
}

function render() {
    usersBody.replaceChildren(...users.map(user => {
        const tr = document.createElement("tr");

        const avatarCell = cell("border avatar-cell");
        const container = document.createElement("div");
        container.className = "avatar-container";
        if (user.avatar) {
            const img = document.createElement("img");
            img.src = user.avatar;
            img.alt = "avatar";
            img.className = "avatar";
            container.append(img);
        } else {
            const none = document.createElement("span");
            none.style.color = "#aaa";
            none.textContent = "Aucun";
            container.append(none);
        }
        avatarCell.append(container);

        const pseudoCell = cell("border");
        pseudoCell.textContent = user.pseudo;
        const emailCell = cell("border");
        emailCell.textContent = user.email;

        const actionCell = cell();
        const buttons = document.createElement("div");
        buttons.className = "btn-container";
        buttons.append(
            iconButton("./assets/image/edit.svg.svg", "modifier", () => openEdit(user.id)),
            iconButton("./assets/image/trash.svg.svg", "supprimer", () => removeUser(user.id))
        );
        actionCell.append(buttons);

        tr.append(avatarCell, pseudoCell, emailCell, actionCell);
        return tr;
    }));
}

function showError(message) {
    errorText.textContent = message || "";
}

function commit(nextUsers) {
    if (!saveUsers(nextUsers)) {
        showError("La démo ne peut plus rien enregistrer dans cet onglet (stockage plein) : supprimez un utilisateur.");
        return false;
    }
    users = nextUsers;
    showError("");
    render();
    return true;
}

// ---------- Ajout ----------
addForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const pseudo = addForm.pseudo.value.trim();
    const email = addForm.email.value.trim();
    const password = addForm.password.value;

    if (!pseudo || !email || !password) return showError("Veuillez remplir tous les champs");
    if (!EMAIL_FORMAT.test(email)) return showError("L'adresse email n'est pas valide");

    try {
        const avatar = await readAvatar(addForm.avatar.files[0]);
        const id = Math.max(0, ...users.map(u => u.id)) + 1;
        if (commit([...users, { id, pseudo, email, avatar }])) addForm.reset();
    } catch (message) {
        showError(message);
    }
});

// ---------- Suppression ----------
function removeUser(id) {
    commit(users.filter(u => u.id !== id));
}

// ---------- Modification (fenêtre par-dessus la liste) ----------
function openEdit(id) {
    const user = users.find(u => u.id === id);
    if (!user) return;

    const overlay = document.querySelector("#edit-modal").content.firstElementChild.cloneNode(true);
    const form = overlay.querySelector("#edit-form");
    form.editPseudo.value = user.pseudo;
    form.editEmail.value = user.email;
    const close = () => overlay.remove();
    overlay.querySelector("[data-close]").addEventListener("click", close);
    // L'erreur s'affiche dans la fenêtre, sinon elle serait cachée dessous
    const modalError = overlay.querySelector(".modal-error");
    const fail = (message) => { modalError.textContent = message; };

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const pseudo = form.editPseudo.value.trim();
        const email = form.editEmail.value.trim();
        if (!pseudo || !email) return fail("Veuillez ne pas laisser de champ vide");
        if (!EMAIL_FORMAT.test(email)) return fail("L'adresse email n'est pas valide");

        try {
            // Nouvel avatar : il remplace l'ancien ; sinon on garde l'ancien
            const newAvatar = await readAvatar(form.editAvatar.files[0]);
            const updated = { ...user, pseudo, email, avatar: newAvatar || user.avatar };
            if (commit(users.map(u => (u.id === id ? updated : u)))) close();
            else fail(errorText.textContent);
        } catch (message) {
            fail(message);
        }
    });

    document.body.append(overlay);
    form.editPseudo.focus();
}

render();
