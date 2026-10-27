
<!-- Tabela de Mecânicos -->
<div class="table-container" id="mecanicos">
    <h2 style="color: #ff4500; margin-bottom: 20px;"> Gestão de Mecânicos</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Especialidade</th>
                <th>Contacto</th>
                <th>Email</th>
                <th>Ordenado Bruto</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($mecanicos_result && $mecanicos_result->num_rows > 0): ?>
                <?php while ($mecanico = $mecanicos_result->fetch_assoc()): ?>
                <tr>
                    <td><?= $mecanico['id_mecanico'] ?></td>
                    <td><?= htmlspecialchars($mecanico['nome_mecanico']) ?></td>
                    <td><?= htmlspecialchars($mecanico['especialidade']) ?></td>
                    <td><?= htmlspecialchars($mecanico['contacto']) ?></td>
                    <td><?= htmlspecialchars($mecanico['email']) ?></td>
                    <td>€ <?= number_format($mecanico['ordenado_bruto'], 2, ',', ' ') ?></td>
                    <td>
                        <a class="btn btn-danger" href="?delete_mecanico=<?= $mecanico['id_mecanico'] ?>" onclick="return confirm('Tem a certeza que pretende eliminar o mecânico <?= htmlspecialchars($mecanico['nome_mecanico']) ?>?')">
                             Eliminar
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:20px;">Nenhum mecânico encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
