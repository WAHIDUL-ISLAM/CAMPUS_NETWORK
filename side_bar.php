<aside class="app-sidebar cn-sidebar p-3" aria-label="Primary">
    <div class="d-flex align-items-center justify-content-between mb-3">

        <a class="d-flex align-items-center gap-2 text-decoration-none cn-logo"
            href="<?php echo rtrim(BASE_URL, '/'); ?>/dashboard/dashboard.php">
            <i class="fa-solid fa-satellite-dish"></i>
            <span class="fw-bold logo-text">Campus Networking</span>
        </a>

        <button class="btn btn-sm btn-light d-md-none" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Open menu">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>

    <nav class="mt-2 cn-nav" aria-label="Sidebar">
        <ul class="list-unstyled d-grid gap-2">
            <li>
                <a class="nav-link active" href="<?php echo rtrim(BASE_URL, '/'); ?>/dashboard/dashboard.php">
                    <i class="fa-solid fa-gauge"></i> <span class="label">Dashboard</span>
                </a>
            </li>
            <li>
                <a class="nav-link" href="<?php echo rtrim(BASE_URL, '/'); ?>/events/event_sidebar.php">
                    <i class="fa-solid fa-calendar-days"></i> <span class="label">Events</span>
                </a>
            </li>
            <li>
                <a class="nav-link" href="<?php echo rtrim(BASE_URL, '/'); ?>/jobs.php">
                    <i class="fa-solid fa-briefcase"></i> <span class="label">Jobs</span>
                </a>
            </li>
            <li>
                <a class="nav-link" href="<?php echo rtrim(BASE_URL, '/'); ?>/success_story/success_story_feed.php">
                    <i class="fa-solid fa-pen-to-square"></i> <span class="label">Posts</span>
                </a>

            </li>
            <li>
                <a class="nav-link" href="<?php echo rtrim(BASE_URL, '/'); ?>/community.php">
                    <i class="fa-solid fa-users"></i> <span class="label">Community</span>
                </a>
            </li>
            <li>
                <a class="nav-link" href="<?php echo rtrim(BASE_URL, '/'); ?>/profile/profile.php">
                    <i class="fa-solid fa-id-badge"></i> <span class="label">Profile</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>