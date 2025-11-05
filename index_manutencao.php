<?php
require_once __DIR__ . '/includes/config/config.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    header('Location: /Site/');
    exit;
}

$usuario_nome = $_SESSION['usuario']['nome'] ?? 'Usuário';
$usuario_id = $_SESSION['usuario']['id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema em Manutenção - Autopel</title>
    <link rel="icon" href="assets/img/logo_site.png" type="image/png">
    <link rel="shortcut icon" href="assets/img/logo_site.png" type="image/png">
    <link rel="apple-touch-icon" href="assets/img/logo_site.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            min-height: 100vh;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #ffffff;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 20px;
            position: relative;
            min-height: 100vh;
            overflow-y: auto;
        }

        .maintenance-container {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
            max-width: 800px;
            width: 100%;
            position: relative;
            z-index: 1;
            animation: fadeIn 0.5s ease-in;
            margin: 20px auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-container {
            margin-bottom: 5px;
        }

        .logo-container img {
            max-width: 100px;
            height: auto;
        }

        .maintenance-icon {
            font-size: 35px;
            margin-bottom: 5px;
        }

        .maintenance-title {
            font-size: 1.2rem;
            color: #1a237e;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .maintenance-message {
            font-size: 0.85rem;
            color: #555;
            line-height: 1.4;
            margin-bottom: 10px;
        }

        .progress-container {
            margin: 10px 0;
            background: #e0e0e0;
            border-radius: 50px;
            height: 5px;
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(90deg, #1a237e, #3949ab);
            height: 100%;
            width: 50%;
            border-radius: 50px;
        }

        .info-box {
            background: #f5f5f5;
            border-left: 4px solid #1a237e;
            padding: 8px;
            border-radius: 6px;
            margin-top: 8px;
            text-align: left;
        }

        .info-box p {
            margin-bottom: 2px;
            color: #666;
            font-size: 0.75rem;
        }

        .info-box p:last-child {
            margin-bottom: 0;
        }

        /* Jogo Snake compacto */
        .game-container {
            margin-top: 8px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 10px;
            border: 2px solid #1a237e;
        }

        .game-title {
            font-size: 0.9rem;
            color: #1a237e;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .game-score {
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 5px;
        }

        #gameCanvas {
            border: 3px solid #1a237e;
            border-radius: 10px;
            background: #1a1a2e;
            display: block;
            margin: 0 auto;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.5);
            max-width: 100%;
            height: auto;
        }

        .game-controls {
            margin-top: 8px;
            font-size: 0.7rem;
            color: #777;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 5px;
        }

        .btn-control {
            padding: 4px 10px;
            background: #1a237e;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-control:hover {
            background: #3949ab;
        }

        .btn-play {
            margin-top: 8px;
            padding: 8px 25px;
            background: linear-gradient(135deg, #1a237e, #3949ab);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(26, 35, 126, 0.3);
        }

        .btn-play:hover {
            background: linear-gradient(135deg, #3949ab, #5c6bc0);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 35, 126, 0.4);
        }

        .btn-play:disabled {
            background: #ccc;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Leaderboard */
        .leaderboard-container {
            margin-top: 8px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 10px;
            border: 2px solid #1a237e;
            max-height: 400px;
            overflow-y: auto;
        }

        .leaderboard-title {
            font-size: 0.9rem;
            color: #1a237e;
            margin-bottom: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .leaderboard-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .leaderboard-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 8px;
            margin-bottom: 4px;
            background: #fff;
            border-radius: 6px;
            font-size: 0.75rem;
            transition: all 0.2s;
        }

        .leaderboard-item:hover {
            background: #e8eaf6;
            transform: translateX(2px);
        }

        .leaderboard-item.me {
            background: #e3f2fd;
            border-left: 3px solid #1a237e;
            font-weight: 600;
        }

        .leaderboard-pos {
            font-weight: bold;
            color: #1a237e;
            min-width: 25px;
        }

        .leaderboard-pos.top1 {
            color: #ffd700;
            font-size: 0.85rem;
        }

        .leaderboard-pos.top2 {
            color: #c0c0c0;
            font-size: 0.85rem;
        }

        .leaderboard-pos.top3 {
            color: #cd7f32;
            font-size: 0.85rem;
        }

        .leaderboard-name {
            flex: 1;
            text-align: left;
            margin-left: 8px;
            color: #333;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .leaderboard-score {
            font-weight: 600;
            color: #1a237e;
            min-width: 40px;
            text-align: right;
        }

        .leaderboard-loading {
            text-align: center;
            padding: 10px;
            color: #666;
            font-size: 0.75rem;
        }

        .leaderboard-empty {
            text-align: center;
            padding: 10px;
            color: #999;
            font-size: 0.75rem;
            font-style: italic;
        }

        .btn-refresh {
            background: none;
            border: none;
            color: #1a237e;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 2px 6px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .btn-refresh:hover {
            background: #e8eaf6;
        }

        @media (min-width: 769px) {
            .maintenance-container {
                padding: 30px;
            }
            
            .logo-container img {
                max-width: 120px;
            }
            
            .maintenance-title {
                font-size: 1.5rem;
            }
            
            .maintenance-message {
                font-size: 1rem;
            }
            
            .maintenance-icon {
                font-size: 45px;
            }
            
            .info-box {
                padding: 12px;
            }
            
            .info-box p {
                font-size: 0.85rem;
            }
            
            .game-container {
                padding: 15px;
            }
            
            .game-title {
                font-size: 1.1rem;
            }
            
            .game-score {
                font-size: 0.85rem;
            }
            
            #gameCanvas {
                width: 300px;
                height: 300px;
            }
            
            .leaderboard-container {
                padding: 15px;
                max-height: 500px;
            }
            
            .leaderboard-title {
                font-size: 1rem;
            }
            
            .leaderboard-item {
                padding: 8px 10px;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .maintenance-container {
                padding: 15px;
                max-width: 95%;
                margin: 10px auto;
            }

            .maintenance-title {
                font-size: 1.1rem;
            }

            .maintenance-message {
                font-size: 0.8rem;
            }

            .maintenance-icon {
                font-size: 30px;
            }

            .logo-container img {
                max-width: 90px;
            }

            #gameCanvas {
                width: 250px;
                height: 250px;
            }

            .btn-play {
                width: 100%;
            }

            .leaderboard-container {
                max-height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="logo-container">
            <img src="assets/img/LOGO AUTOPEL VETOR-01.png" alt="Autopel">
        </div>
        
        <div class="maintenance-icon">🔧</div>
        
        <h1 class="maintenance-title">Sistema em Manutenção</h1>
        
        <p class="maintenance-message">
            Nosso sistema está temporariamente indisponível para manutenção e melhorias.
            Estamos trabalhando para voltar em breve com uma experiência ainda melhor.
        </p>

        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>

        <div class="info-box">
            <p><strong>⏱️ Tempo estimado:</strong> 30 min a 2 horas</p>
            <p><strong>🔄 Status:</strong> Atualizações em andamento</p>
            <p><strong>✅ Ações:</strong> Melhorias de performance</p>
        </div>

        <!-- Jogo Snake -->
        <div class="game-container">
            <div class="game-title">🐍 Snake Game</div>
            <div class="game-score">Pontuação: <span id="score">0</span> | Recorde: <span id="highScore">0</span></div>
            <canvas id="gameCanvas"></canvas>
            <button class="btn-play" id="btnPlay" onclick="initGame()">Começar Jogo</button>
            <div class="game-controls">
                <button class="btn-control" onclick="changeDirection('up')">↑</button>
                <button class="btn-control" onclick="changeDirection('left')">←</button>
                <button class="btn-control" onclick="changeDirection('down')">↓</button>
                <button class="btn-control" onclick="changeDirection('right')">→</button>
            </div>
        </div>

        <!-- Leaderboard -->
        <div class="leaderboard-container">
            <div class="leaderboard-title">
                <span>🏆 Ranking</span>
                <button class="btn-refresh" onclick="loadLeaderboard()" title="Atualizar">🔄</button>
            </div>
            <div id="leaderboardContent" class="leaderboard-loading">Carregando...</div>
        </div>
    </div>
    
    <script>
        // Snake Game
        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');
        const scoreElement = document.getElementById('score');
        const highScoreElement = document.getElementById('highScore');
        const btnPlay = document.getElementById('btnPlay');
        
        const gridSize = 15;
        let tileCount = 16; // Valor inicial, será ajustado
        
        let snake = [{x: 10, y: 10}];
        let food = {};
        let dx = 0;
        let dy = 0;
        let nextDx = 0;
        let nextDy = 0;
        let score = 0;
        let highScore = localStorage.getItem('snakeHighScore') || 0;
        let gameRunning = false;
        let gameLoop;
        
        // Carregar recorde
        highScoreElement.textContent = highScore;

        function randomFood() {
            food = {
                x: Math.floor(Math.random() * tileCount),
                y: Math.floor(Math.random() * tileCount)
            };
            // Garantir que a comida não apareça na cobra
            while (snake.some(segment => segment.x === food.x && segment.y === food.y)) {
                food = {
                    x: Math.floor(Math.random() * tileCount),
                    y: Math.floor(Math.random() * tileCount)
                };
            }
        }

        function drawGrid() {
            ctx.strokeStyle = '#0f3460';
            ctx.lineWidth = 0.5;
            for (let i = 0; i <= tileCount; i++) {
                ctx.beginPath();
                ctx.moveTo(i * gridSize, 0);
                ctx.lineTo(i * gridSize, canvas.height);
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(0, i * gridSize);
                ctx.lineTo(canvas.width, i * gridSize);
                ctx.stroke();
            }
        }

        function drawGame() {
            clearCanvas();
            drawGrid();
            drawFood();
            drawSnake();
        }

        function clearCanvas() {
            ctx.fillStyle = '#1a1a2e';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }

        function drawFood() {
            ctx.fillStyle = '#ff6b6b';
            ctx.shadowColor = '#ff6b6b';
            ctx.shadowBlur = 10;
            ctx.beginPath();
            ctx.arc(
                food.x * gridSize + gridSize / 2,
                food.y * gridSize + gridSize / 2,
                gridSize / 2 - 1,
                0,
                2 * Math.PI
            );
            ctx.fill();
            ctx.shadowBlur = 0;
        }

        function drawSnake() {
            snake.forEach((segment, index) => {
                if (index === 0) {
                    // Cabeça da cobra
                    ctx.fillStyle = '#51cf66';
                    ctx.shadowColor = '#51cf66';
                    ctx.shadowBlur = 8;
                } else {
                    // Corpo da cobra
                    const intensity = 1 - (index / snake.length) * 0.5;
                    ctx.fillStyle = `rgba(81, 207, 102, ${intensity})`;
                }
                
                ctx.fillRect(
                    segment.x * gridSize + 1,
                    segment.y * gridSize + 1,
                    gridSize - 2,
                    gridSize - 2
                );
                ctx.shadowBlur = 0;
            });
        }

        function moveSnake() {
            // Aplicar próxima direção
            dx = nextDx;
            dy = nextDy;
            
            // Se ainda não começou a se mover, não fazer nada
            if (dx === 0 && dy === 0) {
                return;
            }
            
            const head = {x: snake[0].x + dx, y: snake[0].y + dy};
            
            // Colisão com paredes
            if (head.x < 0 || head.x >= tileCount || head.y < 0 || head.y >= tileCount) {
                gameOver();
                return;
            }
            
            // Colisão consigo mesmo
            if (snake.some(segment => segment.x === head.x && segment.y === head.y)) {
                gameOver();
                return;
            }
            
            snake.unshift(head);
            
            // Come comida
            if (head.x === food.x && head.y === food.y) {
                score++;
                scoreElement.textContent = score;
                randomFood();
                
                // Atualizar recorde
                if (score > highScore) {
                    highScore = score;
                    highScoreElement.textContent = highScore;
                    localStorage.setItem('snakeHighScore', highScore);
                }
            } else {
                snake.pop();
            }
        }

        function changeDirection(dir) {
            if (!gameRunning) return;
            
            switch(dir) {
                case 'up':
                    if (dy === 0) { nextDx = 0; nextDy = -1; }
                    break;
                case 'down':
                    if (dy === 0) { nextDx = 0; nextDy = 1; }
                    break;
                case 'left':
                    if (dx === 0) { nextDx = -1; nextDy = 0; }
                    break;
                case 'right':
                    if (dx === 0) { nextDx = 1; nextDy = 0; }
                    break;
            }
        }

        function gameOver() {
            gameRunning = false;
            btnPlay.textContent = 'Jogar Novamente';
            btnPlay.disabled = false;
            
            if (gameLoop) clearInterval(gameLoop);
            
            ctx.fillStyle = 'rgba(0, 0, 0, 0.85)';
            ctx.fillRect(0, canvas.height/2 - 40, canvas.width, 80);
            
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 24px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Game Over!', canvas.width/2, canvas.height/2 - 5);
            
            ctx.font = '14px Arial';
            ctx.fillText(`Pontuação: ${score}`, canvas.width/2, canvas.height/2 + 20);
            
            // Salvar pontuação no servidor
            if (score > 0) {
                saveScore(score);
            }
            
            // Atualizar leaderboard
            loadLeaderboard();
        }

        function initGame() {
            if (gameRunning) return;
            
            snake = [{x: 10, y: 10}];
            dx = 0;
            dy = 0;
            nextDx = 0;
            nextDy = 0;
            score = 0;
            scoreElement.textContent = score;
            gameRunning = true;
            btnPlay.textContent = 'Jogando...';
            btnPlay.disabled = true;
            
            randomFood();
            if (gameLoop) clearInterval(gameLoop);
            
            gameLoop = setInterval(() => {
                moveSnake();
                drawGame();
            }, 120);
            
            drawGame();
        }

        // Controles de teclado
        document.addEventListener('keydown', (e) => {
            if (!gameRunning && e.key !== ' ') {
                return;
            }
            
            switch(e.key) {
                case 'ArrowUp':
                    if (dy === 0) { nextDx = 0; nextDy = -1; }
                    e.preventDefault();
                    break;
                case 'ArrowDown':
                    if (dy === 0) { nextDx = 0; nextDy = 1; }
                    e.preventDefault();
                    break;
                case 'ArrowLeft':
                    if (dx === 0) { nextDx = -1; nextDy = 0; }
                    e.preventDefault();
                    break;
                case 'ArrowRight':
                    if (dx === 0) { nextDx = 1; nextDy = 0; }
                    e.preventDefault();
                    break;
                case ' ': // Espaço para iniciar/jogar novamente
                    if (!gameRunning) initGame();
                    e.preventDefault();
                    break;
            }
        });

        // Funções do Leaderboard
        function saveScore(pontuacao) {
            // Determinar o caminho base
            const basePath = window.location.pathname.includes('/Site/') ? '/Site/' : '/';
            const url = basePath + 'includes/ajax/snake_leaderboard_ajax.php';
            
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('pontuacao', pontuacao);
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        console.log('Pontuação salva:', pontuacao);
                    } else {
                        console.error('Erro ao salvar pontuação:', data.message);
                    }
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e, 'Resposta:', text);
                }
            })
            .catch(error => {
                console.error('Erro ao salvar pontuação:', error);
            });
        }

        function loadLeaderboard() {
            const content = document.getElementById('leaderboardContent');
            if (!content) {
                console.error('Elemento leaderboardContent não encontrado');
                return;
            }
            
            content.innerHTML = '<div class="leaderboard-loading">Carregando...</div>';
            
            // Determinar o caminho base
            const basePath = window.location.pathname.includes('/Site/') ? '/Site/' : '/';
            const url = basePath + 'includes/ajax/snake_leaderboard_ajax.php?action=get&limit=10';
            
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            displayLeaderboard(data.leaderboard, data.minha_pontuacao);
                        } else {
                            console.error('Erro no leaderboard:', data.message);
                            content.innerHTML = '<div class="leaderboard-empty">Erro: ' + (data.message || 'Erro ao carregar ranking') + '</div>';
                        }
                    } catch (e) {
                        console.error('Erro ao parsear JSON:', e, 'Resposta:', text);
                        content.innerHTML = '<div class="leaderboard-empty">Erro ao processar resposta do servidor</div>';
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar leaderboard:', error);
                    content.innerHTML = '<div class="leaderboard-empty">Erro ao carregar ranking: ' + error.message + '</div>';
                });
        }

        function displayLeaderboard(leaderboard, minhaPontuacao) {
            const content = document.getElementById('leaderboardContent');
            const usuarioNome = '<?php echo addslashes($usuario_nome); ?>';
            
            if (!leaderboard || leaderboard.length === 0) {
                content.innerHTML = '<div class="leaderboard-empty">Nenhuma pontuação ainda. Seja o primeiro!</div>';
                return;
            }
            
            let html = '<ul class="leaderboard-list">';
            
            leaderboard.forEach((item, index) => {
                const pos = index + 1;
                const isMe = item.usuario_nome === usuarioNome;
                let posClass = '';
                let posIcon = pos + '. ';
                
                if (pos === 1) {
                    posClass = 'top1';
                    posIcon = '🥇 ';
                } else if (pos === 2) {
                    posClass = 'top2';
                    posIcon = '🥈 ';
                } else if (pos === 3) {
                    posClass = 'top3';
                    posIcon = '🥉 ';
                }
                
                html += `<li class="leaderboard-item ${isMe ? 'me' : ''}">
                    <span class="leaderboard-pos ${posClass}">${posIcon}</span>
                    <span class="leaderboard-name">${escapeHtml(item.usuario_nome)}</span>
                    <span class="leaderboard-score">${item.pontuacao}</span>
                </li>`;
            });
            
            html += '</ul>';
            
            if (minhaPontuacao && minhaPontuacao > 0) {
                const minhaPosicao = leaderboard.findIndex(item => item.usuario_nome === usuarioNome) + 1;
                if (minhaPosicao === 0) {
                    html += `<div style="margin-top: 8px; padding: 6px; background: #e3f2fd; border-radius: 6px; font-size: 0.75rem; text-align: center;">
                        <strong>Sua melhor pontuação:</strong> ${minhaPontuacao} pontos
                    </div>`;
                }
            }
            
            content.innerHTML = html;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Ajustar canvas ao tamanho da tela
        function adjustCanvasSize() {
            const container = canvas.parentElement;
            const maxWidth = container.offsetWidth - 40; // Considerar padding
            const defaultSize = 300;
            
            if (window.innerWidth >= 769) {
                canvas.width = Math.min(defaultSize, maxWidth);
                canvas.height = canvas.width;
            } else {
                canvas.width = Math.min(240, maxWidth);
                canvas.height = canvas.width;
            }
            
            tileCount = canvas.width / gridSize;
            randomFood();
            clearCanvas();
            drawGrid();
            drawFood();
            drawSnake();
        }
        
        // Inicializar canvas
        adjustCanvasSize();
        
        // Ajustar canvas ao redimensionar a janela
        window.addEventListener('resize', () => {
            adjustCanvasSize();
        });
        
        // Carregar leaderboard ao carregar a página
        loadLeaderboard();
    </script>
</body>
</html>

