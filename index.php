<?php
declare(strict_types=1);
require_once __DIR__ . '/src/helpers.php';

$services = db()->query('SELECT name, description, price, duration_minutes FROM services WHERE is_active = 1 ORDER BY id')->fetchAll();
page_start('Your neighborhood barbershop');
?>
<section class="hero hero-home" id="home">
    <div class="hero-copy">
        <p class="eyebrow">YOUR NEIGHBORHOOD BARBERSHOP</p>
        <h1>A good cut.<br><em>A better day.</em></h1>
        <p class="hero-description">Good people, sharp work, and a chair waiting for you. Find your time and come on in.</p>
        <a class="button button-dark" href="<?= current_user() ? 'book_appointment.php' : 'register.php' ?>">Book an appointment <span aria-hidden="true">↗</span></a>
        <p class="walk-ins"><span class="status-dot"></span> Choose a service, barber, and open time</p>
    </div>
    <div class="hero-art" role="img" aria-label="Illustration of a barber chair in a neighborhood barbershop">
        <div class="sun-disc"></div><div class="window-frame"><span></span><span></span><span></span><span></span></div>
        <div class="plant"><i></i><i></i><i></i><b></b></div><div class="chair-shadow"></div>
        <div class="barber-chair"><div class="chair-back"></div><div class="chair-seat"></div><div class="chair-arm"></div><div class="chair-post"></div><div class="chair-base"></div></div>
        <div class="art-caption">TAKE A SEAT <span>CORNER CHAIR</span></div>
    </div>
</section>
<section class="details" id="services">
    <div class="section-heading"><p class="eyebrow">THE GOOD STUFF</p><h2>Make time for<br><em>yourself.</em></h2></div>
    <div class="service-list">
        <?php if ($services === []): ?>
            <p class="empty-state">Services are being updated. Please check back soon.</p>
        <?php else: ?>
            <?php foreach ($services as $index => $service): ?>
                <article class="service-row">
                    <span class="service-number"><?= h(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                    <div><h3><?= h($service['name']) ?></h3><p><?= h($service['description']) ?> · <?= (int) $service['duration_minutes'] ?> min</p></div>
                    <strong>$<?= h(number_format((float) $service['price'], 2)) ?></strong>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<section class="booking" id="about">
    <div class="booking-intro"><p class="eyebrow">YOUR CHAIR IS WAITING</p><h2>Let's get you<br><em>in the book.</em></h2><p>Choose an open time that works for you.</p><div class="shop-hours"><span>BARBER HOURS</span><span>Set by the shop</span></div></div>
    <div class="booking-form intro-actions"><p>Ready to book your next cut?</p><a class="button button-orange" href="<?= current_user() ? 'book_appointment.php' : 'register.php' ?>">Find an appointment <span aria-hidden="true">↗</span></a><p class="form-note">Your appointment details stay in your account.</p></div>
</section>
<?php page_end(); ?>