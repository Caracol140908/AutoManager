<?php
session_start();

try {
    $dbConfig = require __DIR__ . '/config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['database'],
        $dbConfig['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

if(empty($_SESSION['user']) || !isset($_SESSION['user']['id_utilizador'])) {
    header('Location: login.php');
    exit;
}

// Atualizar estado via AJAX
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['id_servico'], $_POST['novo_estado'])){
    $stmt = $pdo->prepare("UPDATE servico SET estado=? WHERE id=?");
    $stmt->execute([$_POST['novo_estado'], (int)$_POST['id_servico']]);
    echo json_encode(['success'=>true]);
    exit;
}

$nome_utilizador = htmlspecialchars($_SESSION['user']['nome'] ?? 'Admin');

$stmt = $pdo->prepare("SELECT id, nome, tipo_servico, data_desejada, estado FROM servico ORDER BY data_desejada DESC LIMIT 10");
$stmt->execute();
$marcacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hoje = date('Y-m-d');
$total_hoje = $pdo->prepare("SELECT COUNT(*) AS total FROM servico WHERE data_desejada=?");
$total_hoje->execute([$hoje]);
$total_hoje = $total_hoje->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$total_clientes = $pdo->query("SELECT COUNT(DISTINCT nome) AS total FROM servico")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_concluidos = $pdo->query("SELECT COUNT(*) AS total FROM servico WHERE estado='Concluído'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>
<!doctype html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>AUTOMANAGER — Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="dashboard.css">
</head>
<body>

<header class="mobile-header">
  <img src="automanagerlogowhite.png" alt="AUTOMANAGER Logo" class="mobile-logo">
  <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
</header>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <img src="automanagerlogowhite.png" alt="AUTOMANAGER Logo" class="sidebar-logo">
    <h2>AUTOMANAGER</h2>
    <button id="closeSidebar" style="display: none; background: none; border: none; color: #fff; font-size: 1.5rem; position: absolute; top: 20px; right: 20px; cursor: pointer;">&times;</button>
  </div>
  <nav class="menu">
    <a href="#" class="active"> Painel</a>
    <a href="#"> Marcações</a>
    <a href="#"> Clientes</a>
    <a href="#"> Serviços</a>
    <a href="#"> Configurações</a>
    <a href="logout.php" class="logout"> Terminar Sessão</a>
  </nav>
</aside>

<main class="dashboard">
  <header class="dashboard-header">
    <h1>Bem-vindo, <span><?= $nome_utilizador ?></span> </h1>
    <p>Gestão de marcações e clientes AUTOMANAGER</p>
  </header>

  <section class="stats">
    <div class="stat-card">
      <h3> Marcações Hoje</h3>
      <strong><?= $total_hoje ?></strong>
      <span>Hoje</span>
    </div>
    <div class="stat-card">
      <h3> Clientes Ativos</h3>
      <strong><?= $total_clientes ?></strong>
      <span>Total registados</span>
    </div>
    <div class="stat-card">
      <h3> Serviços Concluídos</h3>
      <strong><?= $total_concluidos ?></strong>
      <span>Até agora</span>
    </div>
  </section>

  <section class="table-section">
    <h2> Marcações Recentes</h2>
    <table class="data-table">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Serviço</th>
          <th>Data</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($marcacoes as $row):
            $estado = strtolower($row['estado']);
            $classe = $estado==='pendente'?'status-pendente':($estado==='em progresso'?'status-emprogresso':'status-concluido');
        ?>
        <tr>
          <td><?= htmlspecialchars($row['nome']) ?></td>
          <td><?= htmlspecialchars($row['tipo_servico']) ?></td>
          <td><?= date('d/m/Y', strtotime($row['data_desejada'])) ?></td>
          <td>
            <select class="status-select <?= $classe ?>" data-id="<?= $row['id'] ?>">
              <option value="Pendente" <?= $estado==='pendente'?'selected':'' ?>>Pendente</option>
              <option value="Em Progresso" <?= $estado==='em progresso'?'selected':'' ?>>Em Progresso</option>
              <option value="Concluído" <?= $estado==='concluido' || $estado==='concluído'?'selected':'' ?>>Concluído</option>
            </select>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>

<script>
// Toggle Sidebar on Mobile
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const closeSidebar = document.getElementById('closeSidebar');

if (menuToggle) {
  menuToggle.addEventListener('click', () => {
    sidebar.classList.add('active');
  });
}

if (closeSidebar) {
  closeSidebar.addEventListener('click', () => {
    sidebar.classList.remove('active');
  });
}

// Show close button on mobile
function handleMobileView() {
  if (window.innerWidth <= 900) {
    closeSidebar.style.display = 'block';
  } else {
    closeSidebar.style.display = 'none';
    sidebar.classList.remove('active');
  }
}

window.addEventListener('resize', handleMobileView);
handleMobileView();

document.querySelectorAll('select.status-select').forEach(select=>{
  select.addEventListener('change',function(){
    const id = this.dataset.id;
    const novoEstado = this.value;
    fetch('dashboard.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`id_servico=${id}&novo_estado=${encodeURIComponent(novoEstado)}`
    }).then(res=>res.json()).then(data=>{
      if(data.success){
        this.classList.remove('status-pendente','status-emprogresso','status-concluido');
        if(novoEstado==='Pendente') this.classList.add('status-pendente');
        else if(novoEstado==='Em Progresso') this.classList.add('status-emprogresso');
        else if(novoEstado==='Concluído') this.classList.add('status-concluido');
      }
    });
  });
});
</script>
</body>
</html>
