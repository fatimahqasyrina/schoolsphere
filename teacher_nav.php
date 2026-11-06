<?php
$teacherCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');
$teacherAccountUser = $_SESSION['user'] ?? [];
$teacherTaskCount=0;
try { $taskCountQ=$pdo->prepare("SELECT COUNT(*) FROM event_tasks WHERE assignedTo=? AND taskStatus IN ('Tentative','Active')"); $taskCountQ->execute([(int)($teacherAccountUser['userID']??0)]); $teacherTaskCount=(int)$taskCountQ->fetchColumn(); } catch (Throwable $ignore) {}
$teacherAccountPhoto = null; $teacherUnreadCount = 0;
try { $teacherAccountQuery=$pdo->prepare('SELECT profilePhoto FROM users WHERE userID=?'); $teacherAccountQuery->execute([(int)($teacherAccountUser['userID']??0)]); $teacherAccountPhoto=$teacherAccountQuery->fetchColumn()?:null; $teacherUnreadQuery=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE userID=? AND isRead=0'); $teacherUnreadQuery->execute([(int)($teacherAccountUser['userID']??0)]); $teacherUnreadCount=(int)$teacherUnreadQuery->fetchColumn(); } catch (Throwable $ignore) {}
$teacherNavigation = [
    'teacher_dashboard.php' => 'Home',
    'teacher_events.php' => 'My Events',
    'teacher_registrations.php' => 'Registrations',
    'teacher_tasks.php' => 'Committee Tasks',
    'teacher_attendance.php' => 'Attendance',
    'teacher_payments.php' => 'Payment Review',
    'teacher_reports.php' => 'Reports',
    'calendar.php' => 'Calendar',
];
?>
<aside class="teacher-sidebar">
    <a href="teacher_dashboard.php" class="teacher-brand"><span><strong>SchoolSphere</strong><small>SMK Tengku Lela Segara</small></span></a>
    <p class="teacher-menu-label">MAIN MENU</p>
    <nav class="teacher-nav" aria-label="Teacher navigation">
        <?php foreach ($teacherNavigation as $teacherHref => $teacherLabel): ?>
            <a class="teacher-nav-item<?= $teacherCurrentPage === $teacherHref ? ' active' : '' ?>" href="<?= htmlspecialchars($teacherHref, ENT_QUOTES, 'UTF-8') ?>"<?= $teacherCurrentPage === $teacherHref ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($teacherLabel, ENT_QUOTES, 'UTF-8') ?><?= $teacherHref==='teacher_tasks.php' && $teacherTaskCount ? '<span class="teacher-nav-count">'.$teacherTaskCount.'</span>' : '' ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="teacher-sidebar-bottom"><p>Manage school events and review student participation.</p><a href="logout.php">Log Out</a></div>
</aside>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.teacher-topbar').forEach(function (topbar) {
        if (!topbar.querySelector('.teacher-account')) { var account=document.createElement('div');account.className='teacher-account';var label=topbar.querySelector('strong');if(!label){label=document.createElement('strong');label.textContent=<?=json_encode((string)($teacherAccountUser['name']??''))?>;}account.appendChild(label);topbar.appendChild(account); }
    });
    document.querySelectorAll('.teacher-topbar .teacher-account').forEach(function (account) {
        var inbox = document.createElement('a'); inbox.className = 'teacher-inbox-link'; inbox.href = 'inbox.php'; inbox.textContent = 'Inbox';
        var unread = <?= (int)$teacherUnreadCount ?>;
        if (unread) { var badge = document.createElement('span'); badge.textContent = unread > 99 ? '99+' : String(unread); inbox.appendChild(badge); }
        account.prepend(inbox);
        var avatar = account.querySelector('.teacher-avatar'); var photoPath=<?= json_encode((string)$teacherAccountPhoto) ?>;
        if (!avatar) { avatar=document.createElement(photoPath?'img':'span');avatar.className='teacher-avatar';if(photoPath){avatar.src=photoPath;avatar.alt='';}else{avatar.textContent=<?=json_encode(strtoupper(substr((string)($teacherAccountUser['name']??''),0,1)))?>;}inbox.after(avatar); }
        else if (photoPath && avatar.tagName==='SPAN') { var photo=document.createElement('img');photo.className='teacher-avatar';photo.src=photoPath;photo.alt='';avatar.replaceWith(photo); }
        var name = account.querySelector('strong'); var profile = account.querySelector('.teacher-profile-link');
        if (name) { profile=document.createElement('a');profile.className='teacher-profile-link';profile.href='teacher_profile.php';profile.innerHTML=name.outerHTML;name.replaceWith(profile); }
        var role=account.querySelector('small'); if(!role){role=document.createElement('small');role.textContent='Teacher';}
        role.classList.add('teacher-role-label');
        var identity=role.parentElement;
        if(!identity||identity===account){identity=document.createElement('span');identity.className='teacher-account-identity';account.appendChild(identity);}
        identity.classList.add('teacher-account-identity');
        if(role.parentElement!==identity)identity.appendChild(role);
        if(profile&&profile.parentElement!==identity)identity.insertBefore(profile,role);
    });
    var main=document.querySelector('.teacher-main');
    if(main){var footer=main.querySelector('.teacher-footer');if(!footer){footer=document.createElement('footer');footer.className='teacher-footer';}if(footer.parentElement!==main){main.appendChild(footer);}footer.innerHTML='<strong>SMK Tengku Lela Segara · SchoolSphere</strong><span><a href="tel:+6096182227">09-618 2227</a> · <a href="mailto:tea4014@moe.edu.my">tea4014@moe.edu.my</a></span><span><a href="https://smktls.blogspot.com/" target="_blank" rel="noopener">Official website</a> · <a href="https://www.facebook.com/195530113841302" target="_blank" rel="noopener">Facebook</a></span>'+(new Date().getFullYear());}
});
</script>
<script src="assets/confirm-actions.js?v=1" defer></script>






