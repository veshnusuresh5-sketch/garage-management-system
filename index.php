<?php include 'includes/header.php'; ?>
<section class="hero-grid">
    <div class="hero-copy">
        <small>911 Garage Command</small>
        <h1>Car-first service, executive performance, luxury garage control.</h1>
        <p>911 Garage is designed for premium automotive operators. Manage customers, track job cards, run stock inventory, and book high-end service for cars only.</p>
        <div class="hero-actions">
            <a class="btn-primary" href="login.php"><i class='bx bx-tachometer'></i> Launch Dashboard</a>
            <a class="btn-secondary" href="register.php"><i class='bx bx-user-plus'></i> Create Customer Account</a>
        </div>
    </div>
    <div class="hero-panel">
        <div style="position: relative; width: 100%; height: 100%; display: grid; place-items: center;">
            <div style="width: min(460px, 100%); padding: 30px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 28px; backdrop-filter: blur(20px);">
                <div style="display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 24px;">
                    <div>
                        <small style="color: var(--primary-red); text-transform: uppercase; font-weight: 700; letter-spacing: 1.2px;">Garage telemetry</small>
                        <h2 style="margin: 10px 0 0; font-size: 1.75rem;">Premium car service status</h2>
                    </div>
                    <span class="status-pill status-ready" style="font-size: 0.85rem;">Ready</span>
                </div>
                <div style="display: grid; gap: 18px;">
                    <div style="display: flex; justify-content: space-between; gap: 12px; font-size: 0.95rem;"><span>Active bays</span><strong>12</strong></div>
                    <div style="display: flex; justify-content: space-between; gap: 12px; font-size: 0.95rem;"><span>Mechanics online</span><strong>7</strong></div>
                    <div style="display: flex; justify-content: space-between; gap: 12px; font-size: 0.95rem;"><span>Customer rating</span><strong>4.9 / 5</strong></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="track-grid">
    <div class="track-card">
        <div class="label">Service queue</div>
        <div class="value">24 active</div>
        <p style="margin-top: 16px; color: var(--muted);">Performance bookings are auto-balanced to minimize wait time.</p>
    </div>
    <div class="track-card">
        <div class="label">Inventory control</div>
        <div class="value">128 parts</div>
        <p style="margin-top: 16px; color: var(--muted);">Spare counts and low-stock alerts keep your shop supplied.</p>
    </div>
    <div class="track-card">
        <div class="label">Customer portal</div>
        <div class="value">Service requests</div>
        <p style="margin-top: 16px; color: var(--muted);">Customers book service and track their car repair history seamlessly.</p>
    </div>
</section>

<section class="dashboard-panel">
    <div class="panel-title">
        <div>
            <h2>911 Garage features</h2>
            <small>High-performance portal, built for cars</small>
        </div>
    </div>
    <div class="feature-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
        <div style="display: flex; gap: 14px; align-items: flex-start;"><i class='bx bx-car' style="color: var(--primary-red); font-size: 1.5rem;"></i><div><strong>Car-only vehicle management</strong><p style="margin: 6px 0 0; color: var(--muted);">Register only car, SUV, EV, and hybrid vehicles in the system.</p></div></div>
        <div style="display: flex; gap: 14px; align-items: flex-start;"><i class='bx bx-package' style="color: var(--primary-red); font-size: 1.5rem;"></i><div><strong>Stock and spare parts</strong><p style="margin: 6px 0 0; color: var(--muted);">Update inventory counts, pricing, and low-stock thresholds.</p></div></div>
        <div style="display: flex; gap: 14px; align-items: flex-start;"><i class='bx bx-wrench' style="color: var(--primary-red); font-size: 1.5rem;"></i><div><strong>Mechanic assignment</strong><p style="margin: 6px 0 0; color: var(--muted);">Add specialists, assign orders, and monitor service load.</p></div></div>
        <div style="display: flex; gap: 14px; align-items: flex-start;"><i class='bx bx-file-find' style="color: var(--primary-red); font-size: 1.5rem;"></i><div><strong>Job cards and tracking</strong><p style="margin: 6px 0 0; color: var(--muted);">Track active work orders with modern garage telemetry.</p></div></div>
    </div>
</section>

<section class="pricing-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
    <div class="service-card">
        <h4>Quick Pit Tune</h4>
        <p style="margin: 12px 0 18px; color: var(--muted);">Rapid diagnostics and brake service.</p>
        <div class="stat-value">₹3,999</div>
    </div>
    <div class="service-card">
        <h4>Performance Overhaul</h4>
        <p style="margin: 12px 0 18px; color: var(--muted);">Engine calibration and full garage checkout.</p>
        <div class="stat-value">₹8,999</div>
    </div>
    <div class="service-card">
        <h4>Executive Elite</h4>
        <p style="margin: 12px 0 18px; color: var(--muted);">Complete luxury service with telemetry report.</p>
        <div class="stat-value">₹14,999</div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
