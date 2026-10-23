<div class="modal-overlay" id="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="document.getElementById('modal').classList.remove('active')">×</span>
        <h2 style="margin-bottom:24px;">Настройки</h2>
        <div style="margin-bottom:32px;">
            <h3>Подписка</h3>
            <?php if ($isSubscribed): ?>
                <p style="margin-bottom:16px;">Активна до: <strong><?= date('d.m.Y', strtotime($user['subscription_until'])) ?></strong></p>
                <form method="POST">
                    <button type="submit" name="cancel_sub" class="btn-danger">Отменить</button>
                </form>
            <?php else: ?>
                <p style="color:#ef4444;">Неактивна</p>
            <?php endif; ?>
        </div>
        <div>
            <h3>Оформить / Продлить</h3>
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="payForm" style="margin-top:16px;">
                <div class="tariff-grid">
                    <label class="tariff-btn active"><input type="radio" name="plan" value="personal" checked onclick="updateTotal()"> Личный</label>
                    <label class="tariff-btn"><input type="radio" name="plan" value="corporate" onclick="updateTotal()"> Корпоративный</label>
                </div>
                <div id="corporateOptions" style="display:none; background:rgba(0,0,0,0.2); padding:16px; border-radius:12px; margin-bottom:16px; border:1px solid var(--border-color);">
                    <div style="display:flex; justify-content:space-between; font-size:0.9rem; color:var(--text-muted); margin-bottom:8px;">
                        <span>Сотрудников:</span>
                        <span id="seatCount">5</span>
                    </div>
                    <input type="range" name="seats" min="5" max="20" value="5" id="seatSlider" oninput="updateTotal()" style="width:100%; accent-color:var(--accent-purple);">
                </div>
                <div class="tariff-grid" style="margin-top:16px;">
                    <label class="tariff-btn active"><input type="radio" name="months" value="1" checked onclick="updateTotal()"> 1 месяц</label>
                    <label class="tariff-btn"><input type="radio" name="months" value="6" onclick="updateTotal()"> 6 месяцев</label>
                    <label class="tariff-btn"><input type="radio" name="months" value="12" onclick="updateTotal()"> 12 месяцев (скидка 20%)</label>
                </div>
                <div id="finalPrice" style="font-size:1.5rem; font-weight:bold; color:var(--accent-green); text-align:center; margin:24px 0;">200 ₽</div>
                <button type="submit" name="pay" class="btn btn-primary" style="width:100%;">Оплатить</button>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.tariff-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const parent = this.parentElement;
        parent.querySelectorAll('.tariff-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        updateTotal();
    });
});
function updateTotal() {
    const plan = document.querySelector('input[name="plan"]:checked').value;
    const months = parseInt(document.querySelector('input[name="months"]:checked').value);
    const seats = parseInt(document.getElementById('seatSlider').value);
    document.getElementById('corporateOptions').style.display = (plan === 'corporate') ? 'block' : 'none';
    document.getElementById('seatCount').textContent = seats;
    let totalPrice = 0;
    if (plan === 'personal') {
        totalPrice = 200 * months;
    } else {
        let pricePerMonth = 800 + (seats - 5) * 100;
        totalPrice = pricePerMonth * months;
        if (months === 12) {
            totalPrice = totalPrice * 0.8;
        }
    }
    document.getElementById('finalPrice').textContent = totalPrice.toLocaleString('ru-RU') + ' ₽';
}
updateTotal();
</script>