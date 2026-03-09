<?php
session_start();

// Определяем папку для хранения данных
define('DATA_DIR', __DIR__ . '/data');
if (!file_exists(DATA_DIR)) mkdir(DATA_DIR, 0777, true);

// Функции для работы с JSON-файлами
function loadUsers() {
    $file = DATA_DIR . '/users.json';
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}
function saveUsers($users) {
    file_put_contents(DATA_DIR . '/users.json', json_encode($users, JSON_PRETTY_PRINT));
}
function loadItems() {
    $file = DATA_DIR . '/items.json';
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}
function saveItems($items) {
    file_put_contents(DATA_DIR . '/items.json', json_encode($items, JSON_PRETTY_PRINT));
}

// Обработка API-запросов (если передан параметр action)
if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'register') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $users = loadUsers();
        if (isset($users[$username])) {
            echo json_encode(['success' => false, 'message' => 'Имя уже занято']);
        } else {
            $users[$username] = password_hash($password, PASSWORD_DEFAULT);
            saveUsers($users);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    if ($action === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $users = loadUsers();
        if (isset($users[$username]) && password_verify($password, $users[$username])) {
            $_SESSION['user'] = $username;
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Неверное имя или пароль']);
        }
        exit;
    }

    if ($action === 'logout') {
        unset($_SESSION['user']);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get_user') {
        if (isset($_SESSION['user'])) {
            echo json_encode(['loggedIn' => true, 'user' => ['username' => $_SESSION['user']]]);
        } else {
            echo json_encode(['loggedIn' => false]);
        }
        exit;
    }

    if ($action === 'get_items') {
        $type = $_GET['type'] ?? 'all';
        $items = loadItems();
        if ($type !== 'all') {
            $items = array_filter($items, fn($item) => $item['type'] === $type);
        }
        echo json_encode(array_values($items));
        exit;
    }

    if ($action === 'add_item') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => 'Не авторизован']);
            exit;
        }
        $items = loadItems();
        $newItem = [
            'id' => uniqid(),
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'price' => (float)($_POST['price'] ?? 0),
            'type' => $_POST['type'] ?? 'virtual',
            'seller' => $_SESSION['user']
        ];
        $items[] = $newItem;
        saveItems($items);
        echo json_encode(['success' => true]);
        exit;
    }

    // Неизвестное действие
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Маркетплейс вещей</title>
    <style>
        /* Все стили внутри одного файла */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        header { background: #333; color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center; }
        #auth button { margin-left: 10px; padding: 5px 10px; }
        main { padding: 20px; }
        #filters { background: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        #itemsList { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .item-card { background: white; border-radius: 5px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .item-card h3 { margin-top: 0; }
        .item-card .type { font-size: 0.9em; color: #666; }
        .item-card .price { font-weight: bold; color: #2c3e50; }
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 300px; border-radius: 5px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        #addItemSection { background: white; padding: 20px; border-radius: 5px; margin-top: 20px; }
        #addItemForm input, #addItemForm textarea, #addItemForm select { width: 100%; padding: 8px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        #addItemForm button { background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <header>
        <h1>Торговая площадка</h1>
        <div id="auth"></div>
    </header>
    <main>
        <section id="filters">
            <h2>Фильтры</h2>
            <select id="typeFilter">
                <option value="all">Все товары</option>
                <option value="virtual">Виртуальные</option>
                <option value="real">Реальные</option>
            </select>
            <button id="applyFilter">Применить</button>
        </section>
        <section id="items">
            <h2>Объявления</h2>
            <div id="itemsList"></div>
        </section>
        <section id="addItemSection" style="display: none;">
            <h2>Добавить объявление</h2>
            <form id="addItemForm">
                <input type="text" id="itemName" placeholder="Название товара" required>
                <textarea id="itemDescription" placeholder="Описание" required></textarea>
                <input type="number" id="itemPrice" placeholder="Цена" required>
                <select id="itemType" required>
                    <option value="virtual">Виртуальный</option>
                    <option value="real">Реальный</option>
                </select>
                <button type="submit">Опубликовать</button>
            </form>
        </section>
    </main>

    <!-- Модальные окна -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Вход</h2>
            <form id="loginForm">
                <input type="text" id="loginUsername" placeholder="Имя пользователя" required>
                <input type="password" id="loginPassword" placeholder="Пароль" required>
                <button type="submit">Войти</button>
            </form>
        </div>
    </div>
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Регистрация</h2>
            <form id="registerForm">
                <input type="text" id="regUsername" placeholder="Имя пользователя" required>
                <input type="password" id="regPassword" placeholder="Пароль" required>
                <button type="submit">Зарегистрироваться</button>
            </form>
        </div>
    </div>

    <script>
        // Весь JavaScript внутри одного файла
        let currentUser = null;

        const authDiv = document.getElementById('auth');
        const itemsList = document.getElementById('itemsList');
        const addItemSection = document.getElementById('addItemSection');
        const loginModal = document.getElementById('loginModal');
        const registerModal = document.getElementById('registerModal');
        const closeButtons = document.querySelectorAll('.close');

        document.addEventListener('DOMContentLoaded', () => {
            checkAuth();
            loadItems();

            // Обработчики кнопок будут добавляться динамически после checkAuth
        });

        function checkAuth() {
            fetch('?action=get_user')
                .then(res => res.json())
                .then(data => {
                    if (data.loggedIn) {
                        currentUser = data.user;
                        authDiv.innerHTML = `<span>Привет, ${currentUser.username}</span>
                                             <button id="logoutBtn">Выйти</button>
                                             <button id="showAddItem">Добавить товар</button>`;
                        document.getElementById('logoutBtn').addEventListener('click', logout);
                        document.getElementById('showAddItem').addEventListener('click', () => {
                            addItemSection.style.display = 'block';
                        });
                    } else {
                        currentUser = null;
                        authDiv.innerHTML = `<button id="showLogin">Вход</button>
                                             <button id="showRegister">Регистрация</button>`;
                        addItemSection.style.display = 'none';
                    }
                    // Перезакрепляем обработчики
                    document.getElementById('showLogin')?.addEventListener('click', () => {
                        loginModal.style.display = 'block';
                    });
                    document.getElementById('showRegister')?.addEventListener('click', () => {
                        registerModal.style.display = 'block';
                    });
                });
        }

        // Закрытие модалок
        closeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                loginModal.style.display = 'none';
                registerModal.style.display = 'none';
            });
        });
        window.addEventListener('click', (e) => {
            if (e.target == loginModal) loginModal.style.display = 'none';
            if (e.target == registerModal) registerModal.style.display = 'none';
        });

        // Логин
        document.getElementById('loginForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const username = document.getElementById('loginUsername').value;
            const password = document.getElementById('loginPassword').value;
            const formData = new URLSearchParams();
            formData.append('action', 'login');
            formData.append('username', username);
            formData.append('password', password);

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loginModal.style.display = 'none';
                    checkAuth();
                    loadItems();
                } else {
                    alert('Ошибка: ' + data.message);
                }
            });
        });

        // Регистрация
        document.getElementById('registerForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const username = document.getElementById('regUsername').value;
            const password = document.getElementById('regPassword').value;
            const formData = new URLSearchParams();
            formData.append('action', 'register');
            formData.append('username', username);
            formData.append('password', password);

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    registerModal.style.display = 'none';
                    alert('Регистрация успешна, теперь войдите');
                } else {
                    alert('Ошибка: ' + data.message);
                }
            });
        });

        // Выход
        function logout() {
            fetch('?action=logout')
                .then(() => checkAuth());
        }

        // Загрузка товаров
        function loadItems() {
            const type = document.getElementById('typeFilter').value;
            fetch(`?action=get_items&type=${type}`)
                .then(res => res.json())
                .then(items => {
                    itemsList.innerHTML = '';
                    items.forEach(item => {
                        const card = document.createElement('div');
                        card.className = 'item-card';
                        card.innerHTML = `
                            <h3>${item.name}</h3>
                            <p>${item.description}</p>
                            <p class="type">${item.type === 'virtual' ? 'Виртуальный' : 'Реальный'}</p>
                            <p class="price">${item.price} руб.</p>
                            <p><small>Продавец: ${item.seller}</small></p>
                        `;
                        itemsList.appendChild(card);
                    });
                });
        }

        // Добавление товара
        document.getElementById('addItemForm').addEventListener('submit', (e) => {
            e.preventDefault();
            if (!currentUser) {
                alert('Необходимо войти');
                return;
            }
            const name = document.getElementById('itemName').value;
            const description = document.getElementById('itemDescription').value;
            const price = document.getElementById('itemPrice').value;
            const type = document.getElementById('itemType').value;
            const formData = new URLSearchParams();
            formData.append('action', 'add_item');
            formData.append('name', name);
            formData.append('description', description);
            formData.append('price', price);
            formData.append('type', type);

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('addItemForm').reset();
                    addItemSection.style.display = 'none';
                    loadItems();
                } else {
                    alert('Ошибка: ' + data.message);
                }
            });
        });

        // Фильтр
        document.getElementById('applyFilter').addEventListener('click', loadItems);
    </script>
</body>
</html>
