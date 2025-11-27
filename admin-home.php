<?php
session_start();
require_once 'config.php';
checkAdminAuth();
$currentUser = getCurrentUser();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Senior High Admin Panel - Quarter-Based Materials</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* ALL YOUR EXISTING CSS STYLES REMAIN EXACTLY THE SAME */
    :root { color-scheme: light dark; }
    .section{animation:fade .2s ease}
    @keyframes fade{from{opacity:0;transform:translateY(4px)}to{opacity:1}}
    .card{border:1px solid rgb(229 231 235/1);border-radius:1rem;background:white;padding:1rem}
    .stat{font-size:1.75rem;font-weight:700}
    .muted{font-size:.8rem;color:rgb(107 114 128/1)}
    .inp{border:1px solid rgb(229 231 235/1);border-radius:.75rem;padding:.6rem .8rem;background:transparent}
    .nav-btn{width:100%;text-align:left;padding:.6rem .8rem;border-radius:.75rem;border:1px solid rgb(229 231 235/1);font-weight:500}
    .nav-btn:hover{background:rgb(243 244 246/1)}
    .nav-btn.active{background:rgb(79 70 229/1);color:white;border-color:transparent}
    .btn-sm{font-size:.75rem;border:1px solid rgb(229 231 235/1);padding:.25rem .5rem;border-radius:.5rem}
    .btn-sm.warn{background:rgb(251 191 36/1);border-color:transparent;color:black}
    .btn-sm.danger{background:rgb(239 68 68/1);border-color:transparent;color:white}
    .material-card{border-left:4px solid rgb(79 70 229/1);}
    .assignment-card{border-left:4px solid rgb(16 185 129/1);}
    .quarter-badge{font-size:0.7rem;padding:0.2rem 0.5rem;border-radius:0.375rem;font-weight:600;}
    .quarter-1{background-color:#e0f2fe;color:#0369a1;}
    .quarter-2{background-color:#dcfce7;color:#166534;}
    .quarter-3{background-color:#fef3c7;color:#92400e;}
    .quarter-4{background-color:#fce7f3;color:#9d174d;}
    
    /* File Upload Styles */
    .file-input-container {
      position: relative;
      display: flex;
      gap: 0.5rem;
    }
    
    .file-input-hidden {
      position: absolute;
      opacity: 0;
      width: 0.1px;
      height: 0.1px;
      overflow: hidden;
    }
    
    .file-input-button {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgb(243 244 246);
      border: 1px solid rgb(229 231 235);
      border-radius: 0.75rem;
      padding: 0.6rem 1rem;
      cursor: pointer;
      font-size: 0.875rem;
      transition: all 0.2s;
      white-space: nowrap;
    }
    
    .file-input-button:hover {
      background: rgb(229 231 235);
    }
    
    .file-name-display {
      flex: 1;
      padding: 0.6rem 0.8rem;
      border: 1px solid rgb(229 231 235);
      border-radius: 0.75rem;
      background: white;
      font-size: 0.875rem;
      color: rgb(107 114 128);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    
    .file-name-display.has-file {
      color: rgb(17 24 39);
      font-weight: 500;
    }
    
    .file-upload-status {
      font-size: 0.75rem;
      margin-top: 0.25rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .file-upload-progress {
      flex: 1;
      height: 4px;
      background: rgb(229 231 235);
      border-radius: 2px;
      overflow: hidden;
    }
    
    .file-upload-progress-bar {
      height: 100%;
      background: rgb(16 185 129);
      transition: width 0.3s ease;
    }
    
    @media (prefers-color-scheme: dark) {
      .file-input-button {
        background: rgb(31 41 55);
        border-color: rgb(31 41 55);
        color: rgb(156 163 175);
      }
      
      .file-input-button:hover {
        background: rgb(55 65 81);
      }
      
      .file-name-display {
        background: rgb(17 24 39);
        border-color: rgb(31 41 55);
        color: rgb(156 163 175);
      }
      
      .file-name-display.has-file {
        color: rgb(243 244 246);
      }
      
      .file-upload-progress {
        background: rgb(31 41 55);
      }
    }

    /* Notification Styles */
    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: #ef4444;
      color: white;
      border-radius: 50%;
      width: 18px;
      height: 18px;
      font-size: 0.7rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .notification-item {
      animation: slideIn 0.3s ease;
      border-left: 4px solid;
    }
    
    .notification-success { border-left-color: #10b981; }
    .notification-warning { border-left-color: #f59e0b; }
    .notification-error { border-left-color: #ef4444; }
    .notification-info { border-left-color: #3b82f6; }
    
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
      from { transform: translateX(0); opacity: 1; }
      to { transform: translateX(100%); opacity: 0; }
    }
    
    .notification-archive-item {
      transition: all 0.2s ease;
    }
    
    .notification-archive-item:hover {
      background: rgb(243 244 246);
    }
    /* Support Ticket Modal Styles */
#supportTicketModal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: none; /* Start hidden */
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

#supportTicketModal:not(.hidden) {
    display: flex; /* Show when not hidden */
}

#supportTicketModal.hidden {
    display: none; /* Hide when hidden class is present */
}

/* Modal content styling */
#supportTicketModal .bg-white {
    background: white;
    border-radius: 1rem;
    padding: 0;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    margin: 20px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

@media (prefers-color-scheme: dark) {
    #supportTicketModal .bg-white {
        background: rgb(17 24 39);
        border: 1px solid rgb(31 41 55);
    }
}

/* Ensure backdrop click works */
#supportTicketModal::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
}
    /* Sidebar Layout - FIXED TO EDGES */
    #sidebar {
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      width: 240px;
      border-right: 1px solid rgb(229 231 235);
      background: white;
      transform: translateX(0);
      z-index: 50;
      transition: transform 0.3s ease;
    }
    
    @media (prefers-color-scheme: dark) {
      #sidebar {
        border-right-color: rgb(31 41 55);
        background: rgb(17 24 39);
      }
      .notification-archive-item:hover {
        background: rgb(31 41 55);
      }
    }
    
    @media (max-width: 767px) {
      #sidebar {
        transform: translateX(-100%);
      }
      #sidebar.mobile-open {
        transform: translateX(0);
      }
    }
    
    /* Main content adjustment for fixed sidebar */
    main {
      margin-left: 240px;
    }
    
    @media (max-width: 767px) {
      main {
        margin-left: 0;
      }
    }
    
    @media (prefers-color-scheme: dark){
      .card{border-color:rgb(31 41 55/1);background:rgb(17 24 39/1)}
      .inp{border-color:rgb(31 41 55/1)}
      .nav-btn{border-color:rgb(31 41 55/1)}
      .nav-btn:hover{background:rgb(31 41 55/1)}
      table td, table th{border-bottom-color:rgb(31 41 55/1)}
      .quarter-1{background-color:#1e3a8a;color:#bfdbfe;}
      .quarter-2{background-color:#14532d;color:#bbf7d0;}
      .quarter-3{background-color:#713f12;color:#fde68a;}
      .quarter-4{background-color:#831843;color:#fbcfe8;}
    }
    
    table td, table th{border-bottom:1px solid rgb(229 231 235/1)}
    
    /* Responsive improvements */
    @media (max-width: 480px){
      #section-dashboard table thead th:nth-child(4),
      #section-dashboard table tbody td:nth-child(4){ display:none; }
      #section-students table thead th:nth-child(4),
      #section-students table tbody td:nth-child(4){ display:none; }
      table td, table th{ padding:.5rem .5rem; }
    }
    @media (max-width: 380px){ #sidebar{ width:85% !important; } }

    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
    }
    
    .modal-content {
      background: white;
      border-radius: 1rem;
      padding: 1.5rem;
      max-width: 500px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
    }
    
    @media (prefers-color-scheme: dark) {
      .modal-content {
        background: rgb(17 24 39);
        border: 1px solid rgb(31 41 55);
      }
    }

    /* Course Management Styles */
    .course-item {
      border: 1px solid rgb(229 231 235);
      border-radius: 0.75rem;
      padding: 1rem;
      margin-bottom: 0.75rem;
      background: white;
    }
    
    @media (prefers-color-scheme: dark) {
      .course-item {
        border-color: rgb(31 41 55);
        background: rgb(17 24 39);
      }
    }
    
    .course-header {
      display: flex;
      justify-content: between;
      align-items: center;
      margin-bottom: 0.5rem;
    }
    
    .course-code {
      font-weight: 600;
      color: rgb(79 70 229);
    }
    
    @media (prefers-color-scheme: dark) {
      .course-code {
        color: rgb(129 140 248);
      }
    }
    
    .course-actions {
      display: flex;
      gap: 0.5rem;
    }
    
    .course-details {
      font-size: 0.875rem;
      color: rgb(107 114 128);
    }
    
    @media (prefers-color-scheme: dark) {
      .course-details {
        color: rgb(156 163 175);
      }
    }

    /* Student Subjects Styles */
    .subject-item {
      border: 1px solid rgb(229 231 235);
      border-radius: 0.75rem;
      padding: 1rem;
      margin-bottom: 0.75rem;
      background: white;
      transition: all 0.3s ease;
    }
    
    .subject-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    @media (prefers-color-scheme: dark) {
      .subject-item {
        border-color: rgb(31 41 55);
        background: rgb(17 24 39);
      }
    }
    
    .subject-header {
      display: flex;
      justify-content: between;
      align-items: center;
      margin-bottom: 0.5rem;
    }
    
    .subject-code {
      font-weight: 600;
      color: rgb(79 70 229);
    }
    
    @media (prefers-color-scheme: dark) {
      .subject-code {
        color: rgb(129 140 248);
      }
    }
    
    .subject-actions {
      display: flex;
      gap: 0.5rem;
    }
    
    .subject-details {
      font-size: 0.875rem;
      color: rgb(107 114 128);
    }
    
    @media (prefers-color-scheme: dark) {
      .subject-details {
        color: rgb(156 163 175);
      }
    }

    /* Strand-specific colors */
    .strand-humss { border-left: 4px solid #8b5cf6; }
    .strand-ict { border-left: 4px solid #06b6d4; }
    .strand-stem { border-left: 4px solid #10b981; }
    .strand-tvl { border-left: 4px solid #f59e0b; }
    .strand-tvl-he { border-left: 4px solid #ef4444; }

    /* Course Progress Card Styles */
    .course-progress {
      border: 1px solid rgb(229 231 235);
      border-radius: 0.75rem;
      padding: 1rem;
      background: white;
      transition: all 0.3s ease;
    }
    
    .course-progress:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    @media (prefers-color-scheme: dark) {
      .course-progress {
        border-color: rgb(31 41 55);
        background: rgb(17 24 39);
      }
    }

    /* Search Bar Styles */
    .search-container {
      position: relative;
    }
    
    .search-input {
      padding-left: 2.5rem;
      width: 100%;
    }
    
    .search-icon {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: rgb(107 114 128);
    }

    /* Dropdown Visibility Improvements */
    select.inp {
      background-color: white;
      color: rgb(17 24 39);
    }
    
    @media (prefers-color-scheme: dark) {
      select.inp {
        background-color: rgb(17 24 39);
        color: rgb(243 244 246);
      }
    }
    
    select.inp option {
      background-color: white;
      color: rgb(17 24 39);
    }
    
    @media (prefers-color-scheme: dark) {
      select.inp option {
        background-color: rgb(17 24 39);
        color: rgb(243 244 246);
      }
    }

    /* Enhanced Responsive Design */
    @media (max-width: 640px) {
      .mobile-stack {
        flex-direction: column;
      }
      
      .mobile-full {
        width: 100%;
      }
      
      .mobile-text-center {
        text-align: center;
      }
      
      .mobile-p-2 {
        padding: 0.5rem;
      }
      
      .mobile-grid-1 {
        grid-template-columns: 1fr;
      }
    }

    /* Multiple Subject Selection Styles */
    .subject-checkbox-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem;
      border: 1px solid rgb(229 231 235);
      border-radius: 0.5rem;
      margin-bottom: 0.5rem;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    
    .subject-checkbox-item:hover {
      background: rgb(249 250 251);
    }
    
    .subject-checkbox-item.selected {
      background: rgb(239 246 255);
      border-color: rgb(59 130 246);
    }
    
    .subject-checkbox-info {
      flex: 1;
    }
    
    .subject-checkbox-code {
      font-weight: 600;
      font-size: 0.875rem;
      color: rgb(17 24 39);
    }
    
    .subject-checkbox-name {
      font-size: 0.75rem;
      color: rgb(107 114 128);
    }
    
    .selected-subjects-list {
      max-height: 200px;
      overflow-y: auto;
      border: 1px solid rgb(229 231 235);
      border-radius: 0.5rem;
      padding: 0.75rem;
      background: rgb(249 250 251);
    }
    
    .selected-subject-item {
      display: flex;
      justify-content: between;
      align-items: center;
      padding: 0.5rem;
      background: white;
      border-radius: 0.375rem;
      margin-bottom: 0.5rem;
    }
    
    .selected-subject-item:last-child {
      margin-bottom: 0;
    }
    
    .selected-subject-info {
      flex: 1;
    }
    
    .remove-subject-btn {
      color: rgb(239 68 68);
      background: none;
      border: none;
      cursor: pointer;
      padding: 0.25rem;
      border-radius: 0.25rem;
    }
    
    .remove-subject-btn:hover {
      background: rgb(254 242 242);
    }
    
    @media (prefers-color-scheme: dark) {
      .subject-checkbox-item {
        border-color: rgb(55 65 81);
      }
      
      .subject-checkbox-item:hover {
        background: rgb(31 41 55);
      }
      
      .subject-checkbox-item.selected {
        background: rgb(30 58 138);
        border-color: rgb(59 130 246);
      }
      
      .subject-checkbox-code {
        color: rgb(243 244 246);
      }
      
      .selected-subjects-list {
        border-color: rgb(55 65 81);
        background: rgb(31 41 55);
      }
      
      .selected-subject-item {
        background: rgb(17 24 39);
      }
      
      .remove-subject-btn:hover {
        background: rgb(127 29 29);
      }
    }

    /* Teacher Subjects Section */
    .teacher-subjects-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }
    
    .teacher-subject-card {
      border: 1px solid rgb(229 231 235);
      border-radius: 0.75rem;
      padding: 1rem;
      background: white;
      transition: all 0.3s ease;
    }
    
    .teacher-subject-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    @media (prefers-color-scheme: dark) {
      .teacher-subject-card {
        border-color: rgb(31 41 55);
        background: rgb(17 24 39);
      }
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100 min-h-screen">
  <!-- Topbar -->
  <header class="sticky top-0 z-40 border-b border-gray-200 bg-white/80 backdrop-blur dark:border-gray-800 dark:bg-gray-900/70">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-2 px-4 py-3 flex-wrap">
      <div class="flex items-center gap-3">
        <button id="btnOpenSidebar" class="inline-flex items-center rounded-xl border border-gray-200 px-3 py-2 text-sm hover:bg-gray-100 active:scale-[.99] dark:border-gray-800 dark:hover:bg-gray-800 md:hidden" aria-label="Open navigation">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M3.75 5.25a.75.75 0 0 1 .75-.75h15a.75.75 0 0 1 0 1.5h-15a.75.75 0 0 1-.75-.75Zm0 6a.75.75 0 0 1 .75-.75h15a.75.75 0 0 1 0 1.5h-15a.75.75 0 0 1-.75-.75Zm.75 5.25a.75.75 0 0 0 0 1.5h15a.75.75 0 0 0 0-1.5h-15Z" clip-rule="evenodd" /></svg>
        </button>
        <h1 class="text-lg font-semibold tracking-tight">Senior High Admin Panel</h1>
        <span class="hidden rounded-full bg-indigo-600/10 px-3 py-1 text-xs font-medium text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300 md:inline-block">HUMSS • ICT • STEM • TVL • TVL‑HE</span>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <!-- Notification Bell -->
        <div class="relative">
          <button id="btnNotifications" class="relative rounded-xl border border-gray-200 px-3 py-2 text-sm hover:bg-gray-100 dark:border-gray-800 dark:hover:bg-gray-800">
            <i class="fas fa-bell"></i>
            <span id="notificationCount" class="notification-badge hidden">0</span>
          </button>
          
          <!-- Notification Dropdown -->
          <div id="notificationDropdown" class="absolute right-0 top-12 hidden w-80 rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900 z-50">
            <div class="p-3 border-b border-gray-200 dark:border-gray-800">
              <div class="flex items-center justify-between">
                <h3 class="font-semibold">Notifications</h3>
                <button id="btnViewArchive" class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">View Archive</button>
              </div>
            </div>
            <div id="notificationList" class="max-h-64 overflow-y-auto p-2">
              <div class="text-center text-gray-500 py-4 text-sm">No new notifications</div>
            </div>
            <div class="p-2 border-t border-gray-200 dark:border-gray-800">
              <button id="btnClearNotifications" class="w-full rounded-lg bg-gray-100 px-3 py-2 text-sm hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700">
                Clear All
              </button>
            </div>
          </div>
        </div>
        
        <span class="text-sm text-gray-600 dark:text-gray-300">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></span>
        <button id="btnExport" class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 active:scale-[.99]">Export Data</button>
        <a href="logout.php" class="rounded-xl border border-gray-200 px-3 py-2 text-sm hover:bg-gray-100 dark:border-gray-800 dark:hover:bg-gray-800">Logout</a>
      </div>
    </div>
  </header>

  <div class="flex">
    <!-- Sidebar - FIXED POSITION -->
    <aside id="sidebar" class="hidden md:block">
      <nav class="p-6 space-y-1 h-full">
    <button data-section="dashboard" class="nav-btn active">📊 Dashboard</button>
    <button data-section="students" class="nav-btn">👩‍🎓 Students</button>
    <button data-section="materials" class="nav-btn">📚 Learning Content</button>
    <button data-section="teachers" class="nav-btn">👩‍🏫 Teachers</button>
    <button data-section="student-support" class="nav-btn">🎓 Student Support</button>
    <button data-section="about" class="nav-btn">ℹ️ About</button>
</nav>
    </aside>

    <!-- Main Content - ADJUSTED FOR FIXED SIDEBAR -->
    <main class="flex-1 px-4 py-6 md:px-8">
      <!-- DASHBOARD -->
      <section id="section-dashboard" class="section">
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4">Academic Quarter Overview</h2>
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card quarter-1 border-l-4 border-blue-500">
              <p class="muted">1st Quarter</p>
              <p id="statQ1" class="stat">0 Materials</p>
            </div>
            <div class="card quarter-2 border-l-4 border-green-500">
              <p class="muted">2nd Quarter</p>
              <p id="statQ2" class="stat">0 Materials</p>
            </div>
            <div class="card quarter-3 border-l-4 border-yellow-500">
              <p class="muted">3rd Quarter</p>
              <p id="statQ3" class="stat">0 Materials</p>
            </div>
            <div class="card quarter-4 border-l-4 border-pink-500">
              <p class="muted">4th Quarter</p>
              <p id="statQ4" class="stat">0 Materials</p>
            </div>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="card"><p class="muted">Total Students</p><p id="statTotal" class="stat">0</p></div>
          <div class="card"><p class="muted">HUMSS</p><p id="statHUMSS" class="stat">0</p></div>
          <div class="card"><p class="muted">ICT</p><p id="statICT" class="stat">0</p></div>
          <div class="card"><p class="muted">STEM</p><p id="statSTEM" class="stat">0</p></div>
          <div class="card"><p class="muted">TVL</p><p id="statTVL" class="stat">0</p></div>
          <div class="card"><p class="muted">TVL‑HE</p><p id="statTVLHE" class="stat">0</p></div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
          <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-base font-semibold">Recent Students</h3>
            <div class="overflow-auto">
              <table class="min-w-full text-left text-sm">
                <thead class="sticky top-0 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                  <tr><th class="px-3 py-2">Date</th><th class="px-3 py-2">Name</th><th class="px-3 py-2">Strand</th><th class="px-3 py-2">ID</th></tr>
                </thead>
                <tbody id="recentStudents"></tbody>
              </table>
            </div>
          </div>
          <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-base font-semibold">Upcoming Deadlines</h3>
            <ul id="upcomingDeadlines" class="space-y-2 text-sm"></ul>
          </div>
        </div>
      </section>

      <!-- STUDENTS -->
      <section id="section-students" class="section hidden">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold">Add New Student</h3>
            <div class="text-xs text-gray-500">Data is saved to the database.</div>
          </div>
          <form id="studentForm" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <input name="studentId" required placeholder="Student ID" class="inp" />
            <input name="firstName" required placeholder="First Name" class="inp" />
            <input name="middleName" placeholder="Middle Name" class="inp" />
            <input name="lastName" required placeholder="Last Name" class="inp" />
            <input name="username" required placeholder="Username" class="inp" />
            <input name="password" type="password" required placeholder="Password" class="inp" />
            <input
    name="email"
    type="email"
    required
    placeholder="Student Gmail"
    class="inp"
  />
            <select name="gradeLevel" class="inp" required id="studentGradeLevel">
              <option value="">Select Grade Level</option>
              <option value="11">Grade 11</option>
              <option value="12">Grade 12</option>
            </select>
            <select name="section" class="inp" required id="studentSection">
  <option value="">Select Section</option>
  <option value="A">A</option>
  <option value="B">B</option>
  <option value="C">C</option>
  <option value="D">D</option>
</select>

            <select name="strand" class="inp" required id="studentStrand">
              <option value="">Select Strand</option>
              <option value="HUMSS">HUMSS</option>
              <option value="ICT">ICT</option>
              <option value="STEM">STEM</option>
              <option value="TVL">TVL</option>
              <option value="TVL-HE">TVL-HE</option>
            </select>
            <button type="submit" class="col-span-1 rounded-xl bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 sm:col-span-2 lg:col-span-3">Save Student</button>
          </form>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
          <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
              <h3 class="text-base font-semibold">Browse Students</h3>
              <div class="flex flex-wrap gap-2">
                <div class="search-container">
                  <input type="text" id="searchStudent" placeholder="Search students..." class="inp search-input w-40">
                  <i class="fas fa-search search-icon"></i>
                </div>
                <select id="filterStrand" class="inp w-40">
                  <option value="ALL">All Strands</option>
                  <option value="HUMSS">HUMSS</option>
                  <option value="ICT">ICT</option>
                  <option value="STEM">STEM</option>
                  <option value="TVL">TVL</option>
                  <option value="TVL-HE">TVL-HE</option>
                </select>
              </div>
            </div>
            <div class="overflow-auto">
              <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                  <tr><th class="px-3 py-2">ID</th><th class="px-3 py-2">Name</th><th class="px-3 py-2">Gr/Section</th><th class="px-3 py-2">Strand</th><th class="px-3 py-2">Actions</th></tr>
                </thead>
                <tbody id="studentsTable"></tbody>
              </table>
            </div>
          </div>

          <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="mb-3 text-base font-semibold">Selected Student</h3>
            <div id="studentDetail" class="text-sm text-gray-600 dark:text-gray-300">Select a student to view details…</div>

            <div class="mt-3">
              <label for="studentGradeFromDb" class="block text-xs font-medium text-gray-500 mb-1">
                Grade (from database)
              </label>
              <input
                id="studentGradeFromDb"
                type="text"
                class="inp"
                placeholder="Grade will appear when you select a student"
                readonly
              />
            </div>
            
            <!-- Student Subjects Section -->
            <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold">Student Subjects</h3>
                <button id="btnAddSubject" class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 hidden">
                  <i class="fas fa-plus mr-1"></i> Add Subjects
                </button>
              </div>
              <div id="studentSubjectsList" class="space-y-3">
                <div class="text-center text-gray-500 py-4">Select a student to view subjects</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- LEARNING CONTENT (Combined Materials & Assignments) -->
      <section id="section-materials" class="section hidden">
        <!-- Learning Materials Section -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 mb-6">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold">Upload Learning Material</h3>
            <div class="text-xs text-gray-500">Materials are organized by quarter and directly available to students</div>
          </div>
          <form id="materialForm" class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="space-y-3">
              <input name="title" required placeholder="Material Title" class="inp w-full" />
              <select name="quarter" class="inp w-full" required>
                <option value="">Select Quarter</option>
                <option value="1">1st Quarter</option>
                <option value="2">2nd Quarter</option>
                <option value="3">3rd Quarter</option>
                <option value="4">4th Quarter</option>
              </select>
              <select name="strand" class="inp w-full" required>
                <option value="">Select Strand</option>
                <option value="HUMSS">HUMSS</option>
                <option value="ICT">ICT</option>
                <option value="STEM">STEM</option>
                <option value="TVL">TVL</option>
                <option value="TVL-HE">TVL-HE</option>
              </select>
              <select name="gradeLevel" class="inp w-full" required>
                <option value="">Select Grade Level</option>
                <option value="11">Grade 11</option>
                <option value="12">Grade 12</option>
                <option value="ALL">All Grades</option>
              </select>
            </div>
            <div class="space-y-3">
              <select name="subject" class="inp w-full" required disabled>
                <option value="">Please select Strand, Grade Level and Quarter first</option>
              </select>
              <select name="type" class="inp w-full" required>
                <option value="">Material Type</option>
                <option value="Handout">Handout/PDF</option>
                <option value="PPT">PowerPoint Presentation</option>
                <option value="Video">Video Lesson</option>
                <option value="Audio">Audio Lesson</option>
                <option value="Link">External Link</option>
                <option value="Other">Other</option>
              </select>
              
              <!-- File Upload Section for Materials -->
              <div>
                <label class="block text-sm font-medium mb-2">File Attachment</label>
                <div class="file-input-container">
                  <input type="file" id="materialFileInput" name="file" class="file-input-hidden" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.zip,.mp4,.mp3,.jpg,.jpeg,.png">
                  <button type="button" id="materialFileBrowseButton" class="file-input-button">
                    <i class="fas fa-folder-open"></i>
                    Browse Files
                  </button>
                  <div id="materialFileNameDisplay" class="file-name-display">
                    No file selected
                  </div>
                </div>
                <div class="text-xs text-gray-500 mt-1">
                  Supported formats: PDF, DOC, PPT, MP4, MP3, Images, ZIP (Max: 50MB)
                </div>
              </div>
              
              <textarea name="description" rows="3" placeholder="Material Description" class="inp w-full"></textarea>
              <button type="submit" class="w-full rounded-xl bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Upload Material</button>
            </div>
          </form>
        </div>

        

        <!-- Assignments & Tasks Section -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 mb-6">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold">Create Assignment/Task</h3>
            <div class="text-xs text-gray-500">Assignments are organized by quarter and go to teachers for distribution</div>
          </div>
          <form id="assignmentForm" class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="space-y-3">
              <input name="title" required placeholder="Assignment Title" class="inp w-full" />
              <select name="quarter" class="inp w-full" required>
                <option value="">Select Quarter</option>
                <option value="1">1st Quarter</option>
                <option value="2">2nd Quarter</option>
                <option value="3">3rd Quarter</option>
                <option value="4">4th Quarter</option>
              </select>
              <select name="type" class="inp w-full" required>
                <option value="">Task Type</option>
                <option value="Assignment">Assignment</option>
                <option value="Activity">Activity</option>
                <option value="Performance Task">Performance Task</option>
                <option value="Project">Project</option>
                <option value="Quiz">Quiz</option>
              </select>
              <select name="strand" class="inp w-full" required>
                <option value="">Select Strand</option>
                <option value="HUMSS">HUMSS</option>
                <option value="ICT">ICT</option>
                <option value="STEM">STEM</option>
                <option value="TVL">TVL</option>
                <option value="TVL-HE">TVL-HE</option>
              </select>
               <select name="gradeLevel" class="inp w-full" required id="assignmentGradeLevel">
      <option value="">Select Grade Level</option>
      <option value="11">Grade 11</option>
      <option value="12">Grade 12</option>
      <option value="ALL">All Grades</option>
    </select>
            </div>
            <div class="space-y-3">
              <select name="teacherId" class="inp w-full" required id="assignmentTeacherSelect">
                <option value="">Assign to Teacher</option>
              </select>
              <select name="subject" class="inp w-full" required id="assignmentSubjectSelect" disabled>
                <option value="">Please select a teacher first</option>
              </select>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3 w-full">
  <div>
    <label for="assignmentStartDate" class="block text-sm font-medium mb-1">
      Start Date
    </label>
    <input
      name="startDate"
      id="assignmentStartDate"
      type="date"
      class="inp w-full"
      required
    />
  </div>

  <div>
    <label for="assignmentEndDate" class="block text-sm font-medium mb-1">
      End / Due Date
    </label>
    <input
      name="dueDate"
      id="assignmentEndDate"
      type="date"
      class="inp w-full"
      required
    />
  </div>
</div>

              
              <!-- File Upload Section for Assignments -->
              <div>
                <label class="block text-sm font-medium mb-2">Assignment Files (Optional)</label>
                <div class="file-input-container">
                  <input type="file" id="assignmentFileInput" name="file" class="file-input-hidden" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.zip,.mp4,.mp3,.jpg,.jpeg,.png">
                  <button type="button" id="assignmentFileBrowseButton" class="file-input-button">
                    <i class="fas fa-folder-open"></i>
                    Browse Files
                  </button>
                  <div id="assignmentFileNameDisplay" class="file-name-display">
                    No file selected
                  </div>
                </div>
                <div class="text-xs text-gray-500 mt-1">
                  Supported formats: PDF, DOC, PPT, MP4, MP3, Images, ZIP (Max: 200MB)
                </div>
              </div>
              
              <textarea name="instructions" rows="3" placeholder="Instructions & Requirements" class="inp w-full" required></textarea>
              <div class="grid grid-cols-2 gap-3">
                <input name="maxScore" type="number" placeholder="Max Score" class="inp" required />
                <input name="weight" type="number" placeholder="Weight (%)" class="inp" value="100" />
              </div>
              <button type="submit" class="w-full rounded-xl bg-green-600 px-4 py-2 font-medium text-white hover:bg-green-700">Create Assignment</button>
            </div>
          </form>
        </div>

        <!-- Learning Materials Library -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 mb-6">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold">Learning Materials Library</h3>
            <div class="flex flex-wrap gap-2">
              <select id="materialQuarterFilter" class="inp">
                <option value="ALL">All Quarters</option>
                <option value="1">1st Quarter</option>
                <option value="2">2nd Quarter</option>
                <option value="3">3rd Quarter</option>
                <option value="4">4th Quarter</option>
              </select>
              <select id="materialStrandFilter" class="inp">
                <option value="ALL">All Strands</option>
                <option value="HUMSS">HUMSS</option>
                <option value="ICT">ICT</option>
                <option value="STEM">STEM</option>
                <option value="TVL">TVL</option>
                <option value="TVL-HE">TVL-HE</option>
              </select>
              <select id="materialGradeFilter" class="inp">
                <option value="ALL">All Grades</option>
                <option value="11">Grade 11</option>
                <option value="12">Grade 12</option>
              </select>
            </div>
          </div>
          <div id="materialsList" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"></div>
        </div>

        <!-- Active Assignments & Tasks -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold">Active Assignments & Tasks</h3>
            <div class="flex flex-wrap gap-2">
              <select id="assignmentQuarterFilter" class="inp">
                <option value="ALL">All Quarters</option>
                <option value="1">1st Quarter</option>
                <option value="2">2nd Quarter</option>
                <option value="3">3rd Quarter</option>
                <option value="4">4th Quarter</option>
              </select>
              <select id="assignmentStrandFilter" class="inp">
                <option value="ALL">All Strands</option>
                <option value="HUMSS">HUMSS</option>
                <option value="ICT">ICT</option>
                <option value="STEM">STEM</option>
                <option value="TVL">TVL</option>
                <option value="TVL-HE">TVL-HE</option>
              </select>
              <select id="assignmentStatusFilter" class="inp">
                <option value="ALL">All Status</option>
                <option value="active">Active</option>
                <option value="completed">Completed</option>
                <option value="draft">Draft</option>
              </select>
              
            </div>
          </div>
          <div id="assignmentsList" class="space-y-4"></div>
        </div>
      </section>

      <!-- TEACHERS -->
      <section id="section-teachers" class="section hidden">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold">Add New Teacher</h3>
            <div class="text-xs text-gray-500">Data is saved to the database.</div>
          </div>
          <form id="teacherForm" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <input name="firstName" required placeholder="First Name" class="inp" />
            <input name="middleName" placeholder="Middle Name" class="inp" />
            <input name="lastName" required placeholder="Last Name" class="inp" />
            <input name="email" type="email" placeholder="Email (optional)" class="inp" />
            <select name="strand" class="inp" required>
              <option value="HUMSS">HUMSS</option>
              <option value="ICT">ICT</option>
              <option value="STEM">STEM</option>
              <option value="TVL">TVL</option>
              <option value="TVL-HE">TVL-HE</option>
            </select>
            <input name="username" required placeholder="Username (for login)" class="inp" />
            <input name="password" type="password" required placeholder="Password (for login)" class="inp" />
            <button type="submit" class="col-span-1 rounded-xl bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 sm:col-span-2 lg:col-span-3">Save Teacher</button>
          </form>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
          <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
              <h3 class="text-base font-semibold">Browse Teachers</h3>
              <div class="flex flex-wrap gap-2">
                <div class="search-container">
                  <input type="text" id="searchTeacher" placeholder="Search teachers..." class="inp search-input w-40">
                  <i class="fas fa-search search-icon"></i>
                </div>
                <select id="filterTeacherStrand" class="inp w-40">
                  <option value="ALL">All Strands</option>
                  <option value="HUMSS">HUMSS</option>
                  <option value="ICT">ICT</option>
                  <option value="STEM">STEM</option>
                  <option value="TVL">TVL</option>
                  <option value="TVL-HE">TVL-HE</option>
                </select>
              </div>
            </div>
            <div class="overflow-auto">
              <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                  <tr><th class="px-3 py-2">Name</th><th class="px-3 py-2">Strand</th><th class="px-3 py-2">Username</th><th class="px-3 py-2">Actions</th></tr>
                </thead>
                <tbody id="teacherTable"></tbody>
              </table>
            </div>
          </div>

          <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-base font-semibold">Selected Teacher</h3>
            <div id="teacherDetail" class="text-sm text-gray-600 dark:text-gray-300">Select a teacher to view details…</div>
            
            <!-- Teacher Subjects Section -->
            <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold">Teacher Subjects</h3>
                <button id="btnAssignSubjects" class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 hidden">
                  <i class="fas fa-plus mr-1"></i> Assign Subjects
                </button>
              </div>
              <div id="teacherSubjectsList" class="teacher-subjects-grid">
                <div class="text-center text-gray-500 py-4 col-span-full">Select a teacher to view assigned subjects</div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-3 text-base font-semibold">Teacher Workload by Quarter</h3>
          <div id="teacherWorkload" class="space-y-3 text-sm"></div>
        </div>
      </section>

      <!-- DISTRIBUTION -->
      <section id="section-distribution" class="section hidden">
        <div class="grid gap-6 lg:grid-cols-2">
          <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-base font-semibold">Quarterly Distribution Status</h3>
            <div class="space-y-4">
              <div class="p-4 bg-blue-50 rounded-lg dark:bg-blue-900/20">
                <div class="flex items-center justify-between mb-2">
                  <div class="font-medium">1st Quarter Distribution</div>
                  <span class="quarter-badge quarter-1">Q1</span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                  <div>Learning Materials: <span id="q1Materials">0</span></div>
                  <div>Assignments: <span id="q1Assignments">0</span></div>
                  <div>Performance Tasks: <span id="q1Performance">0</span></div>
                </div>
              </div>
              
              <div class="p-4 bg-green-50 rounded-lg dark:bg-green-900/20">
                <div class="flex items-center justify-between mb-2">
                  <div class="font-medium">2nd Quarter Distribution</div>
                  <span class="quarter-badge quarter-2">Q2</span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                  <div>Learning Materials: <span id="q2Materials">0</span></div>
                  <div>Assignments: <span id="q2Assignments">0</span></div>
                  <div>Performance Tasks: <span id="q2Performance">0</span></div>
                </div>
              </div>
              
              <div class="p-4 bg-yellow-50 rounded-lg dark:bg-yellow-900/20">
                <div class="flex items-center justify-between mb-2">
                  <div class="font-medium">3rd Quarter Distribution</div>
                  <span class="quarter-badge quarter-3">Q3</span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                  <div>Learning Materials: <span id="q3Materials">0</span></div>
                  <div>Assignments: <span id="q3Assignments">0</span></div>
                  <div>Performance Tasks: <span id="q3Performance">0</span></div>
                </div>
              </div>
              
              <div class="p-4 bg-pink-50 rounded-lg dark:bg-pink-900/20">
                <div class="flex items-center justify-between mb-2">
                  <div class="font-medium">4th Quarter Distribution</div>
                  <span class="quarter-badge quarter-4">Q4</span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                  <div>Learning Materials: <span id="q4Materials">0</span></div>
                  <div>Assignments: <span id="q4Assignments">0</span></div>
                  <div>Performance Tasks: <span id="q4Performance">0</span></div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-base font-semibold">Quick Distribution by Quarter</h3>
            <form id="quickDistributeForm" class="space-y-3">
              <select name="quarter" class="inp w-full" required>
                <option value="">Select Quarter</option>
                <option value="1">1st Quarter</option>
                <option value="2">2nd Quarter</option>
                <option value="3">3rd Quarter</option>
                <option value="4">4th Quarter</option>
              </select>
              <select name="contentType" class="inp w-full" required>
                <option value="">Select Content Type</option>
                <option value="material">Learning Material</option>
                <option value="assignment">Assignment</option>
                <option value="activity">Activity</option>
                <option value="performance">Performance Task</option>
                <option value="announcement">Announcement</option>
              </select>
              <select name="targetStrand" class="inp w-full" required>
                <option value="ALL">All Strands</option>
                <option value="HUMSS">HUMSS Only</option>
                <option value="ICT">ICT Only</option>
                <option value="STEM">STEM Only</option>
                <option value="TVL">TVL Only</option>
                <option value="TVL-HE">TVL-HE Only</option>
              </select>
              <textarea name="message" rows="3" placeholder="Distribution message or instructions..." class="inp w-full"></textarea>
              <button type="submit" class="w-full rounded-xl bg-purple-600 px-4 py-2 font-medium text-white hover:bg-purple-700">Distribute Now</button>
            </form>
          </div>
        </div>
      </section>

      <!-- STUDENT SUPPORT -->

<!-- STUDENT SUPPORT -->
<section id="section-student-support" class="section hidden">
    <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 mb-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Student Support</h2>
            <p class="text-gray-600 dark:text-gray-300">Manage student account credentials and provide support for login issues</p>
        </div>

        <!-- Search Student -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">Find Student</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="search-container">
                    <input type="text" id="supportStudentSearch" placeholder="Search by Student ID or Name..." class="inp search-input">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <select id="supportStrandFilter" class="inp">
                    <option value="ALL">All Strands</option>
                    <option value="HUMSS">HUMSS</option>
                    <option value="ICT">ICT</option>
                    <option value="STEM">STEM</option>
                    <option value="TVL">TVL</option>
                    <option value="TVL-HE">TVL-HE</option>
                </select>
                <button id="searchSupportStudent" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </div>

        <!-- Student Details and Credential Management -->
        <div id="studentSupportDetails" class="hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Student Information -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-lg font-semibold mb-4">Student Information</h3>
                    <div id="supportStudentInfo" class="space-y-3">
                        <!-- Student info will be loaded here -->
                    </div>
                </div>

                <!-- Credential Management -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-lg font-semibold mb-4">Update Credentials</h3>
                    
                    <!-- Change Username -->
                    <div class="mb-6">
                        <h4 class="font-medium mb-3 text-gray-700 dark:text-gray-300">Change Username</h4>
                        <form id="changeUsernameForm" class="space-y-3">
                            <input type="hidden" id="supportStudentId" name="studentId">
                            <div>
                                <label class="block text-sm font-medium mb-1">Current Username</label>
                                <input type="text" id="currentUsername" class="inp w-full" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">New Username</label>
                                <input type="text" id="newUsername" name="newUsername" class="inp w-full" required minlength="3">
                            </div>
                            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-user-edit mr-2"></i>Update Username
                            </button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <h4 class="font-medium mb-3 text-gray-700 dark:text-gray-300">Reset Password</h4>
                        <form id="resetPasswordForm" class="space-y-3">
                            <input type="hidden" id="passwordStudentId" name="studentId">
                            <div>
                                <label class="block text-sm font-medium mb-1">New Password</label>
                                <input type="password" id="newPassword" name="newPassword" class="inp w-full" required minlength="6">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Confirm Password</label>
                                <input type="password" id="confirmPassword" class="inp w-full" required>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <i class="fas fa-info-circle"></i>
                                <span>Password must be at least 6 characters long</span>
                            </div>
                            <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fas fa-key mr-2"></i>Reset Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-lg font-semibold mb-4">Recent Support Activity</h3>
                <div id="supportActivityLog" class="space-y-2">
                    <div class="text-center text-gray-500 py-4">No recent support activity</div>
                </div>
            </div>
        </div>

        <!-- No Student Selected Message -->
        <div id="noStudentSelected" class="text-center py-12">
            <div class="w-24 h-24 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-user-graduate text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-600 dark:text-gray-400 mb-2">No Student Selected</h3>
            <p class="text-gray-500 dark:text-gray-500">Search for a student to manage their credentials</p>
        </div>
    </div>

    <!-- Support Tickets Section - ALWAYS VISIBLE -->
    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Student Support Tickets</h3>
            <button
                id="refreshSupportTickets"
                type="button"
                class="text-xs px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700"
            >
                <i class="fas fa-rotate-right mr-1"></i>Refresh
            </button>
        </div>

        <!-- Ticket search + filter -->
        <div class="mb-3 flex flex-wrap gap-2">
            <div class="search-container w-full sm:w-auto">
                <input
                    type="text"
                    id="ticketSearch"
                    placeholder="Search tickets by subject, ID, or email..."
                    class="inp search-input w-full sm:w-64"
                />
                <i class="fas fa-search search-icon"></i>
            </div>

            <select id="ticketStatusFilter" class="inp w-full sm:w-40">
                <option value="ALL">All Status</option>
                <option value="open">Open</option>
                <option value="pending">Pending</option>
                <option value="resolved">Resolved</option>
            </select>
        </div>

        <!-- Ticket list -->
        <div
            id="supportTicketList"
            class="divide-y divide-gray-200 dark:divide-gray-800 max-h-72 overflow-y-auto text-sm"
        >
            <div class="text-center text-gray-500 py-4">
                Loading support tickets...
            </div>
        </div>
    </div>
</section>

      <!-- ABOUT -->
      <section id="section-about" class="section hidden">
        <div class="prose prose-sm max-w-none dark:prose-invert">
          <h2>About This Admin Panel</h2>
          <p>This is a comprehensive admin panel for managing Senior High School learning materials and assignments organized by academic quarters.</p>
          
          <h3>Quarter-Based Organization</h3>
          <ul>
            <li><strong>Learning Materials:</strong> Organized by quarter for easy access and management</li>
            <li><strong>Assignments & Tasks:</strong> Categorized by quarter with proper sequencing</li>
            <li><strong>Performance Tasks:</strong> Quarterly tracking and distribution</li>
          </ul>
          
          <h3>Quarter Color Coding</h3>
          <div class="grid grid-cols-2 gap-4 my-4">
            <div class="flex items-center gap-2">
              <span class="quarter-badge quarter-1">Q1</span>
              <span>1st Quarter</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="quarter-badge quarter-2">Q2</span>
              <span>2nd Quarter</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="quarter-badge quarter-3">Q3</span>
              <span>3rd Quarter</span>
            </div>
            <div class="flex items-center gap=2">
              <span class="quarter-badge quarter-4">Q4</span>
              <span>4th Quarter</span>
            </div>
          </div>
        </div>
      </section>

      <!-- NOTIFICATION ARCHIVE SECTION -->
      <section id="section-notifications" class="section hidden">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-semibold">Notification Archive</h3>
            <button id="btnClearArchive" class="rounded-xl bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">Clear Archive</button>
          </div>
          <div id="notificationArchive" class="space-y-2 max-h-96 overflow-y-auto">
            <div class="text-center text-gray-500 py-8">No archived notifications</div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- Drawer backdrop -->
  <div id="backdrop" class="fixed inset-0 z-40 hidden bg-black/40"></div>

  <!-- Edit Student Modal -->
  <div id="editStudentModal" class="modal-overlay hidden">
    <div class="modal-content">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">Edit Student Information</h3>
        <button id="closeEditModal" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="editStudentForm" class="space-y-4">
        <input type="hidden" id="editStudentId" name="id">
        
        <div>
          <label class="block text-sm font-medium mb-1">Student ID</label>
          <input type="text" id="editStudentIdField" name="studentId" required class="inp w-full">
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">First Name</label>
            <input type="text" id="editFirstName" name="firstName" required class="inp w-full">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Middle Name</label>
            <input type="text" id="editMiddleName" name="middleName" class="inp w-full">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Last Name</label>
            <input type="text" id="editLastName" name="lastName" required class="inp w-full">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Username</label>
            <input type="text" id="editUsername" name="username" required class="inp w-full">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Password</label>
            <input type="password" id="editPassword" name="password" class="inp w-full" placeholder="Leave blank to keep current">
          </div>
        </div>
        <div>
    <label class="block text-sm font-medium mb-1">Gmail</label>
    <input
      type="email"
      id="editEmail"
      name="email"
      class="inp w-full"
      placeholder="Student Gmail"
    >
  </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Grade Level</label>
            <select id="editGradeLevel" name="gradeLevel" class="inp w-full" required>
              <option value="11">Grade 11</option>
              <option value="12">Grade 12</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Section</label>
            <select id="editStudentSection" name="section" class="inp" required>
  <option value="">Select Section</option>
  <option value="A">A</option>
  <option value="B">B</option>
  <option value="C">C</option>
  <option value="D">D</option>
</select>
z
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Strand</label>
          <select id="editStrand" name="strand" class="inp w-full" required>
            <option value="HUMSS">HUMSS</option>
            <option value="ICT">ICT</option>
            <option value="STEM">STEM</option>
            <option value="TVL">TVL</option>
            <option value="TVL-HE">TVL-HE</option>
          </select>
        </div>

        <!-- Irregular Student Option -->
        <div>
          <label class="block text-sm font-medium mb-1">Student Type</label>
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-2">
              <input type="radio" id="editRegularStudent" name="isIrregular" value="0" class="rounded border-gray-300" checked>
              <span>Regular Student</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="radio" id="editIrregularStudent" name="isIrregular" value="1" class="rounded border-gray-300">
              <span>Irregular Student</span>
            </label>
          </div>
          <div class="text-xs text-gray-500 mt-1">
            Irregular students can take subjects from different strands and grade levels
          </div>
        </div>

        <div class="flex gap-3 pt-4">
          <button type="submit" class="flex-1 rounded-xl bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
            Update Student
          </button>
          <button type="button" id="cancelEdit" class="flex-1 rounded-xl border border-gray-300 px-4 py-2 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Enhanced Add Subject Modal with Multiple Selection -->
  <div id="addSubjectModal" class="modal-overlay hidden">
    <div class="modal-content" style="max-width: 700px;">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">Add Subjects to Student</h3>
        <button id="closeAddSubjectModal" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="addSubjectForm" class="space-y-4">
        <input type="hidden" id="addSubjectStudentId" name="studentId">
        
        <!-- Multiple Subject Selection -->
        <div>
          <label class="block text-sm font-medium mb-2">Select Subjects</label>
          <div class="mb-3">
            <div class="flex items-center gap-2 mb-2">
              <input type="checkbox" id="selectAllSubjects" class="rounded border-gray-300">
              <label for="selectAllSubjects" class="text-sm font-medium">Select All Available Subjects</label>
            </div>
            <div id="availableSubjectsList" class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-3">
              <div class="text-center text-gray-500 py-8">Loading available subjects...</div>
            </div>
          </div>
          
          <!-- Selected Subjects Preview -->
          <div id="selectedSubjectsSection" class="hidden">
            <label class="block text-sm font-medium mb-2">Selected Subjects (<span id="selectedCount">0</span>)</label>
            <div id="selectedSubjectsList" class="selected-subjects-list">
              <!-- Selected subjects will appear here -->
            </div>
          </div>
        </div>

        <!-- Common Settings for All Selected Subjects -->
        <div class="border-t pt-4">
          <h4 class="text-sm font-medium mb-3 text-gray-700 dark:text-gray-300">Common Settings for All Selected Subjects</h4>
          
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Quarter *</label>
              <select id="subjectQuarter" name="quarter" class="inp w-full" required>
                <option value="1">1st Quarter</option>
                <option value="2">2nd Quarter</option>
                <option value="3">3rd Quarter</option>
                <option value="4">4th Quarter</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Credits</label>
              <input type="number" id="subjectCredits" name="credits" class="inp w-full" value="1" min="1" max="5">
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Teacher</label>
            <select id="subjectTeacher" name="teacherId" class="inp w-full">
              <option value="">Select Teacher (Optional)</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Schedule</label>
            <input type="text" id="subjectSchedule" name="schedule" class="inp w-full" placeholder="e.g., Mon-Wed-Fri 8:00-9:00 AM">
          </div>
        </div>

        <div class="flex gap-3 pt-4">
          <button type="submit" class="flex-1 rounded-xl bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
            Add Selected Subjects
          </button>
          <button type="button" id="cancelAddSubject" class="flex-1 rounded-xl border border-gray-300 px-4 py-2 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Teacher Modal -->
  <div id="editTeacherModal" class="modal-overlay hidden">
    <div class="modal-content">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">Edit Teacher Information</h3>
        <button id="closeEditTeacherModal" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="editTeacherForm" class="space-y-4">
        <input type="hidden" id="editTeacherId" name="id">
        
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div>
            <label class="block text-sm font-medium mb-1">First Name</label>
            <input type="text" id="editTeacherFirstName" name="firstName" required class="inp w-full">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Middle Name</label>
            <input type="text" id="editTeacherMiddleName" name="middleName" class="inp w-full">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Last Name</label>
            <input type="text" id="editTeacherLastName" name="lastName" required class="inp w-full">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Email</label>
          <input type="email" id="editTeacherEmail" name="email" class="inp w-full" placeholder="Optional">
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Username</label>
            <input type="text" id="editTeacherUsername" name="username" required class="inp w-full">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Password</label>
            <input type="password" id="editTeacherPassword" name="password" class="inp w-full" placeholder="Leave blank to keep current">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Strand</label>
          <select id="editTeacherStrand" name="strand" class="inp w-full" required>
            <option value="HUMSS">HUMSS</option>
            <option value="ICT">ICT</option>
            <option value="STEM">STEM</option>
            <option value="TVL">TVL</option>
            <option value="TVL-HE">TVL-HE</option>
          </select>
        </div>

        <div class="flex gap-3 pt-4">
          <button type="submit" class="flex-1 rounded-xl bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
            Update Teacher
          </button>
          <button type="button" id="cancelEditTeacher" class="flex-1 rounded-xl border border-gray-300 px-4 py-2 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Assign Subjects to Teacher Modal -->
  <div id="assignTeacherSubjectsModal" class="modal-overlay hidden">
    <div class="modal-content" style="max-width: 700px;">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">Assign Subjects to Teacher</h3>
        <button id="closeAssignTeacherSubjectsModal" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="assignTeacherSubjectsForm" class="space-y-4">
        <input type="hidden" id="assignTeacherId" name="teacherId">
        
        <!-- Teacher Info -->
        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
          <h4 class="font-medium mb-2">Teacher Information</h4>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-gray-500">Name:</span>
              <span id="assignTeacherName" class="font-medium"></span>
            </div>
            <div>
              <span class="text-gray-500">Strand:</span>
              <span id="assignTeacherStrand" class="font-medium"></span>
            </div>
          </div>
        </div>

        <!-- Multiple Subject Selection -->
        <div>
          <label class="block text-sm font-medium mb-2">Select Subjects to Assign</label>
          <div class="mb-3">
            <div class="flex items-center gap-2 mb-2">
              <input type="checkbox" id="selectAllTeacherSubjects" class="rounded border-gray-300">
              <label for="selectAllTeacherSubjects" class="text-sm font-medium">Select All Available Subjects</label>
            </div>
            <!-- Strand Filter -->
<div class="flex items-center justify-between mb-3 gap-2">
  <label for="teacherStrandFilter" class="text-sm font-medium text-gray-700">
    Filter by Strand:
  </label>
  <select id="teacherStrandFilter" class="border border-gray-300 rounded-md px-2 py-1 text-sm">
  <option value="all">All strands</option>
  <option value="STEM">STEM</option>
  <option value="HUMSS">HUMSS</option>
  <option value="ICT">ICT</option>
  <option value="TVL">TVL</option>
  <option value="TVL-HE">TVL-HE</option>
  <option value="TVL-ICT">TVL-ICT</option>
</select>

</div>

<div id="availableTeacherSubjectsList"></div>

            <div id="availableTeacherSubjectsList" class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-3">
              <div class="text-center text-gray-500 py-8">Loading available subjects...</div>
            </div>
          </div>
          
          <!-- Selected Subjects Preview -->
          <div id="selectedTeacherSubjectsSection" class="hidden">
            <label class="block text-sm font-medium mb-2">Selected Subjects (<span id="selectedTeacherCount">0</span>)</label>
            <div id="selectedTeacherSubjectsList" class="selected-subjects-list">
              <!-- Selected subjects will appear here -->
            </div>
          </div>
        </div>

        <div class="flex gap-3 pt-4">
          <button type="submit" class="flex-1 rounded-xl bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
            Assign Selected Subjects
          </button>
          <button type="button" id="cancelAssignTeacherSubjects" class="flex-1 rounded-xl border border-gray-300 px-4 py-2 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

    <!-- Support Ticket Reply Modal -->
  <div
    id="supportTicketModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40"
  >
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-lg mx-4">
      <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-800">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
          Reply to Support Ticket
        </h3>
        <button
          type="button"
          id="closeSupportTicketModal"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>

      <form id="supportTicketEmailForm" class="px-5 py-4 space-y-4">
        <input type="hidden" id="modalTicketId" />
        <input type="hidden" id="modalStudentEmail" />

        <div class="text-sm text-gray-600 dark:text-gray-300">
          <div class="font-medium mb-1">
            <span id="ticketModalStudentName">Student Name</span>
            <span class="text-gray-400"> · </span>
            <span id="ticketModalStudentId">ID</span>
          </div>
          <div class="text-xs text-gray-500">
            Ticket: <span id="ticketModalSubject"></span>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Email Subject</label>
          <input
            type="text"
            id="ticketEmailSubject"
            class="inp w-full"
            value="Account update for your ALLSHS eLMS account"
            required
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Message to Student</label>
          <textarea
            id="ticketEmailMessage"
            class="inp w-full min-h-[140px]"
            placeholder="Hi [Student Name],

We have updated your account credentials.

Username: [new username]
Temporary Password: [new password]

Please log in and change your password immediately.

Best regards,
ALLSHS eLMS Support"
            required
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-200 dark:border-gray-800">
          <button
            type="button"
            id="cancelSupportTicketModal"
            class="px-4 py-2 rounded-lg text-sm border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-4 py-2 rounded-lg text-sm bg-blue-600 text-white hover:bg-blue-700"
          >
            <i class="fas fa-paper-plane mr-2"></i>Send Email
          </button>
        </div>
      </form>
    </div>
  </div>


  <script>
// ====== CONFIG ======
const STRANDS = ["HUMSS", "ICT", "STEM", "TVL", "TVL-HE"];
const QUARTERS = ["1", "2", "3", "4"];
const API_BASE = 'api/';

// Current selected student for subject management
let currentStudentId = null;
let currentTeacherId = null;

// Store selected subjects for multiple selection
let selectedSubjects = new Set();
let selectedTeacherSubjects = new Set();

// Search functionality
let allStudents = [];
let allTeachers = [];
let supportTickets = [];

// ====== STUDENT SUPPORT FUNCTIONALITY ======

// Store current student data in session storage
function saveCurrentStudent(student) {
    if (student) {
        sessionStorage.setItem('currentSupportStudent', JSON.stringify(student));
    }
}

function getCurrentStudent() {
    const saved = sessionStorage.getItem('currentSupportStudent');
    return saved ? JSON.parse(saved) : null;
}

// Search for students in support section
document.getElementById('searchSupportStudent').addEventListener('click', searchSupportStudent);

async function searchSupportStudent() {
    const searchTerm = document.getElementById('supportStudentSearch').value.trim();
    const strandFilter = document.getElementById('supportStrandFilter').value;

    if (!searchTerm) {
        addNotification('error', 'Search Required', 'Please enter a student ID or name to search');
        return;
    }

    try {
        // Search students API call
        const students = await apiCall(`students.php?search=${encodeURIComponent(searchTerm)}&strand=${strandFilter}`);
        
        if (students.length === 0) {
            addNotification('warning', 'No Students Found', 'No students match your search criteria');
            return;
        }

        if (students.length === 1) {
            // If only one student found, load their details
            loadStudentSupportDetails(students[0].id);
        } else {
            // If multiple students found, show selection modal
            showStudentSelectionModal(students);
        }
    } catch (error) {
        addNotification('error', 'Search Failed', `Failed to search students: ${error.message}`);
    }
}

// Load student details for support
async function loadStudentSupportDetails(studentId) {
    try {
        const student = await apiCall(`students.php?id=${studentId}`);
        
        // Save student to session storage
        saveCurrentStudent(student);
        
        // Update forms with student ID
        document.getElementById('supportStudentId').value = studentId;
        document.getElementById('passwordStudentId').value = studentId;
        document.getElementById('currentUsername').value = student.username || 'Not set';

        // Display student information
        document.getElementById('supportStudentInfo').innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-sm text-gray-500">Student ID</span>
                    <div class="font-medium">${student.student_id}</div>
                </div>
                <div>
                    <span class="text-sm text-gray-500">Full Name</span>
                    <div class="font-medium">${student.full_name}</div>
                </div>
                <div>
                    <span class="text-sm text-gray-500">Strand</span>
                    <div class="font-medium">${student.strand}</div>
                </div>
                <div>
                    <span class="text-sm text-gray-500">Grade & Section</span>
                    <div class="font-medium">Grade ${student.grade_level}${student.section ? ` / ${student.section}` : ''}</div>
                </div>
                <div>
                    <span class="text-sm text-gray-500">Email</span>
                    <div class="font-medium">${student.email || 'Not set'}</div>
                </div>
                <div>
                    <span class="text-sm text-gray-500">Status</span>
                    <div class="font-medium ${student.is_irregular ? 'text-orange-600' : 'text-green-600'}">
                        ${student.is_irregular ? 'Irregular' : 'Regular'}
                    </div>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <span class="text-sm text-gray-500">Last Updated</span>
                <div class="font-medium">${new Date(student.updated_at).toLocaleString()}</div>
            </div>
        `;

        // Show student details section
        document.getElementById('studentSupportDetails').classList.remove('hidden');
        document.getElementById('noStudentSelected').classList.add('hidden');

        // Load support activity log
        loadSupportActivityLog(studentId);

    } catch (error) {
        addNotification('error', 'Load Failed', `Failed to load student details: ${error.message}`);
    }
}

// Restore student on page load/refresh
function restoreStudentSupportState() {
    const currentStudent = getCurrentStudent();
    if (currentStudent && currentStudent.id) {
        loadStudentSupportDetails(currentStudent.id);
    }
}

// Change username form handler
document.getElementById('changeUsernameForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    if (!data.newUsername || data.newUsername.length < 3) {
        addNotification('error', 'Invalid Username', 'Username must be at least 3 characters long');
        return;
    }

    try {
        await apiCall(`students.php?id=${data.studentId}`, 'PUT', {
            username: data.newUsername
        });

        addNotification('success', 'Username Updated', `Username has been updated to: ${data.newUsername}`);
        
        // Update current username display
        document.getElementById('currentUsername').value = data.newUsername;
        
        // Clear form
        document.getElementById('newUsername').value = '';
        
        // Log the activity
        logSupportActivity(data.studentId, 'username_change', `Username updated to: ${data.newUsername}`);

    } catch (error) {
        addNotification('error', 'Update Failed', `Failed to update username: ${error.message}`);
    }
});

// Reset password form handler
document.getElementById('resetPasswordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const studentId = document.getElementById('passwordStudentId').value;

    if (newPassword !== confirmPassword) {
        addNotification('error', 'Password Mismatch', 'New password and confirmation do not match');
        return;
    }

    if (newPassword.length < 6) {
        addNotification('error', 'Invalid Password', 'Password must be at least 6 characters long');
        return;
    }

    try {
        await apiCall(`students.php?id=${studentId}`, 'PUT', {
            password: newPassword
        });

        addNotification('success', 'Password Reset', 'Student password has been reset successfully');
        
        // Clear form
        document.getElementById('newPassword').value = '';
        document.getElementById('confirmPassword').value = '';
        
        // Log the activity
        logSupportActivity(studentId, 'password_reset', 'Password was reset by admin');

    } catch (error) {
        addNotification('error', 'Reset Failed', `Failed to reset password: ${error.message}`);
    }
});

// Show student selection modal (for multiple search results)
function showStudentSelectionModal(students) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Select Student</h3>
                <button onclick="this.closest('.modal-overlay').remove()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                ${students.map(student => `
                    <div class="p-3 border border-gray-200 rounded-lg hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800 cursor-pointer"
                         onclick="selectSupportStudent(${student.id})">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="font-medium">${student.full_name}</div>
                                <div class="text-sm text-gray-500">${student.student_id} • ${student.strand} • Grade ${student.grade_level}</div>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function selectSupportStudent(studentId) {
    document.querySelector('.modal-overlay').remove();
    loadStudentSupportDetails(studentId);
}

// Load support activity log
async function loadSupportActivityLog(studentId) {
    try {
        // In a real implementation, you'd fetch this from an API
        // For now, we'll use localStorage to simulate activity tracking
        const activities = JSON.parse(localStorage.getItem(`support_activities_${studentId}`)) || [];
        
        const container = document.getElementById('supportActivityLog');
        
        if (activities.length === 0) {
            container.innerHTML = '<div class="text-center text-gray-500 py-4">No recent support activity</div>';
            return;
        }
        
        container.innerHTML = activities.map(activity => `
            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full ${getActivityColor(activity.type)} flex items-center justify-center">
                        <i class="fas ${getActivityIcon(activity.type)} text-white text-sm"></i>
                    </div>
                    <div>
                        <div class="font-medium">${activity.description}</div>
                        <div class="text-sm text-gray-500">${new Date(activity.timestamp).toLocaleString()}</div>
                    </div>
                </div>
                <span class="text-xs px-2 py-1 rounded-full ${getActivityBadgeColor(activity.type)}">
                    ${activity.type.replace('_', ' ').toUpperCase()}
                </span>
            </div>
        `).join('');
        
    } catch (error) {
        console.error('Error loading activity log:', error);
    }
}

// Log support activity
function logSupportActivity(studentId, type, description) {
    const activities = JSON.parse(localStorage.getItem(`support_activities_${studentId}`)) || [];
    
    activities.unshift({
        type: type,
        description: description,
        timestamp: new Date().toISOString(),
        admin: 'System Admin' // In real implementation, use actual admin name
    });
    
    // Keep only last 10 activities
    if (activities.length > 10) {
        activities.splice(10);
    }
    
    localStorage.setItem(`support_activities_${studentId}`, JSON.stringify(activities));
    loadSupportActivityLog(studentId);
}

// Helper functions for activity display
function getActivityColor(type) {
    const colors = {
        'username_change': 'bg-blue-500',
        'password_reset': 'bg-green-500',
        'account_locked': 'bg-red-500',
        'default': 'bg-gray-500'
    };
    return colors[type] || colors.default;
}

function getActivityIcon(type) {
    const icons = {
        'username_change': 'fa-user-edit',
        'password_reset': 'fa-key',
        'account_locked': 'fa-lock',
        'default': 'fa-cog'
    };
    return icons[type] || icons.default;
}

function getActivityBadgeColor(type) {
    const colors = {
        'username_change': 'bg-blue-100 text-blue-800',
        'password_reset': 'bg-green-100 text-green-800',
        'account_locked': 'bg-red-100 text-red-800',
        'default': 'bg-gray-100 text-gray-800'
    };
    return colors[type] || colors.default;
}

// ====== SUPPORT TICKETS FUNCTIONALITY ======

// Fetch tickets from backend
async function loadSupportTickets() {
    try {
        const tickets = await apiCall('support-tickets.php');

        if (Array.isArray(tickets)) {
            supportTickets = tickets;
        } else if (tickets && Array.isArray(tickets.tickets)) {
            supportTickets = tickets.tickets;
        } else {
            supportTickets = [];
        }

        renderSupportTickets();
    } catch (err) {
        console.error('Error loading support tickets:', err);
        addNotification('error', 'Tickets Error', 'Failed to load support tickets');
    }
}

function renderSupportTickets() {
    const listEl = document.getElementById('supportTicketList');
    if (!listEl) return;

    const search = (document.getElementById('ticketSearch')?.value || '').trim().toLowerCase();
    const statusFilter = document.getElementById('ticketStatusFilter')?.value || 'ALL';

    let filtered = supportTickets.slice();

    // Filter by status
    if (statusFilter !== 'ALL') {
        filtered = filtered.filter(t => String(t.status || '').toLowerCase() === statusFilter.toLowerCase());
    }

    // Text search
    if (search) {
        filtered = filtered.filter(t => {
            const subject = String(t.subject || '').toLowerCase();
            const desc = String(t.description || '').toLowerCase();
            const email = String(t.student_email || '').toLowerCase();
            const name = String(t.student_name || '').toLowerCase();
            const sid = String(t.student_id || '').toLowerCase();
            return (
                subject.includes(search) ||
                desc.includes(search) ||
                email.includes(search) ||
                name.includes(search) ||
                sid.includes(search)
            );
        });
    }

    if (!filtered.length) {
        listEl.innerHTML = `<div class="text-center text-gray-500 py-4">No support tickets found</div>`;
        return;
    }

    listEl.innerHTML = filtered
        .map(t => {
            const status = (t.status || 'open').toLowerCase();
            const created = t.created_at ? new Date(t.created_at).toLocaleString() : '';
            const badgeClasses =
                status === 'resolved'
                    ? 'bg-green-100 text-green-700'
                    : status === 'pending'
                    ? 'bg-yellow-100 text-yellow-700'
                    : 'bg-blue-100 text-blue-700';

            return `
                <button
                    type="button"
                    class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-start justify-between gap-3"
                    data-ticket-id="${t.id}"
                >
                    <div>
                        <div class="font-medium text-gray-800 dark:text-gray-100">
                            ${t.subject || 'Support Request'}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            ${t.student_name || 'Unknown student'} · ${t.student_id || ''}
                        </div>
                        <div class="text-xs text-gray-400 dark:text-gray-500">
                            ${created}
                        </div>
                    </div>
                    <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${badgeClasses}">
                        ${status.charAt(0).toUpperCase() + status.slice(1)}
                    </span>
                </button>
            `;
        })
        .join('');
}

// Ticket list click -> open modal
const ticketListEl = document.getElementById('supportTicketList');
if (ticketListEl) {
    ticketListEl.addEventListener('click', (e) => {
        const item = e.target.closest('[data-ticket-id]');
        if (!item) return;
        const ticketId = item.getAttribute('data-ticket-id');
        const ticket = supportTickets.find(t => String(t.id) === String(ticketId));
        if (ticket) {
            openSupportTicketModal(ticket);
        }
    });
}

// Open support ticket modal
function openSupportTicketModal(ticket) {
    const modal = document.getElementById('supportTicketModal');
    if (!modal) return;

    // Populate modal with ticket data
    document.getElementById('modalTicketId').value = ticket.id;
    document.getElementById('modalStudentEmail').value = ticket.student_email || '';
    document.getElementById('ticketModalStudentName').textContent = ticket.student_name || 'Unknown Student';
    document.getElementById('ticketModalStudentId').textContent = ticket.student_id || 'N/A';
    document.getElementById('ticketModalSubject').textContent = ticket.subject || 'Support Request';

    // Pre-fill email message
    const messageTextarea = document.getElementById('ticketEmailMessage');
    if (messageTextarea) {
        messageTextarea.value = `Hi ${ticket.student_name || 'Student'},

We have received your support request regarding: "${ticket.subject}"

Here is an update on your issue:

[Your response here]

If you have any further questions, please don't hesitate to contact us.

Best regards,
ALLSHS eLMS Support Team`;
    }

    modal.classList.remove('hidden');
}

// Close support ticket modal
function closeSupportTicketModal() {
    const modal = document.getElementById('supportTicketModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Send email response to student
document.getElementById('supportTicketEmailForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    try {
        const formData = new FormData(e.target);
        const data = {
            ticketId: document.getElementById('modalTicketId').value,
            studentEmail: document.getElementById('modalStudentEmail').value,
            subject: document.getElementById('ticketEmailSubject').value,
            message: document.getElementById('ticketEmailMessage').value
        };

        // Send email via API
        const result = await apiCall('support-tickets.php', 'PUT', data);
        
        addNotification('success', 'Email Sent', 'Response has been sent to the student');
        
        // Close modal and refresh tickets
        closeSupportTicketModal();
        loadSupportTickets();
        
    } catch (error) {
        addNotification('error', 'Email Failed', `Failed to send email: ${error.message}`);
    }
});

// Search & filter listeners
const ticketSearchEl = document.getElementById('ticketSearch');
if (ticketSearchEl) {
    ticketSearchEl.addEventListener('input', () => renderSupportTickets());
}

const ticketStatusFilterEl = document.getElementById('ticketStatusFilter');
if (ticketStatusFilterEl) {
    ticketStatusFilterEl.addEventListener('change', () => renderSupportTickets());
}

const refreshSupportTicketsBtn = document.getElementById('refreshSupportTickets');
if (refreshSupportTicketsBtn) {
    refreshSupportTicketsBtn.addEventListener('click', () => loadSupportTickets());
}

// Modal close handlers
document.getElementById('closeSupportTicketModal').addEventListener('click', closeSupportTicketModal);
document.getElementById('cancelSupportTicketModal').addEventListener('click', closeSupportTicketModal);
document.getElementById('supportTicketModal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('supportTicketModal')) {
        closeSupportTicketModal();
    }
});

// Initialize student support section
function renderStudentSupport() {
    // Load tickets when opening Student Support section
    loadSupportTickets();
    
    // Restore any previously selected student
    restoreStudentSupportState();
}

// ====== TEACHER SUBJECT ASSIGNMENT ======

// Open assign subjects modal for teacher
async function openAssignTeacherSubjectsModal(teacherId) {
    const modal = document.getElementById('assignTeacherSubjectsModal');
    if (!modal) return;

    // Clear any old selection
    selectedTeacherSubjects.clear();
    updateSelectedTeacherSubjectsUI();

    const teacher = await apiCall(`teachers.php?id=${teacherId}`);

    // Populate teacher info
    document.getElementById('assignTeacherId').value = teacherId;
    document.getElementById('assignTeacherName').textContent = teacher.name;
    document.getElementById('assignTeacherStrand').textContent = teacher.strand;

    // Load subjects that are:
    // - unassigned, OR
    // - assigned only to this teacher
    await loadAvailableTeacherSubjects(teacherId, teacher.strand);

    modal.classList.remove('hidden');
}

// Load available subjects for this teacher (exclude ones used by other teachers)
async function loadAvailableTeacherSubjects(teacherId, strand) {
    try {
        const container = document.getElementById('availableTeacherSubjectsList');
        if (container) {
            container.innerHTML = `
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                    <div>Loading subjects...</div>
                </div>
            `;
        }

        // Ask backend for "available" subjects for this teacher
        const endpoint = `teacher-subjects.php?mode=available&teacherId=${teacherId}&strand=${encodeURIComponent(strand || '')}`;
        const subjects = await apiCall(endpoint);

        if (!Array.isArray(subjects) || subjects.length === 0) {
            container.innerHTML = `
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-book-open text-2xl mb-2"></i>
                    <div>No subjects available</div>
                    <div class="text-sm text-gray-400 mt-2">Either all subjects are assigned or none match this strand.</div>
                </div>
            `;
            return;
        }

        // Reset selection, we will re-add the ones already assigned to THIS teacher
        selectedTeacherSubjects.clear();

        // Group by grade level
        const subjectsByGrade = {};
        subjects.forEach(subject => {
            const gradeLevel = subject.grade_level || 'N/A';
            if (!subjectsByGrade[gradeLevel]) {
                subjectsByGrade[gradeLevel] = [];
            }
            subjectsByGrade[gradeLevel].push(subject);
        });

        let html = '';

        Object.keys(subjectsByGrade).sort().forEach(gradeLevel => {
            const gradeSubjects = subjectsByGrade[gradeLevel];
            html += `
                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2 p-2 bg-gray-50 rounded-lg">
                        <i class="fas fa-graduation-cap text-blue-600"></i>
                        <h4 class="font-semibold text-sm text-gray-700">Grade ${gradeLevel} Subjects</h4>
                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                            ${gradeSubjects.length} subjects
                        </span>
                    </div>
                    <div class="space-y-2">
            `;

            gradeSubjects.forEach(subject => {
                const isAssignedToThis = subject.is_assigned_to_teacher === 1 || subject.is_assigned_to_teacher === "1";
                const checkedAttr = isAssignedToThis ? 'checked' : '';

                // If already assigned to this teacher, keep it in Set so it stays selected
                if (isAssignedToThis) {
                    selectedTeacherSubjects.add(String(subject.id));
                }

                html += `
                    <div class="subject-checkbox-item teacher-subject-checkbox-item" data-subject-id="${subject.id}">
                        <input
                            type="checkbox"
                            class="teacher-subject-checkbox rounded border-gray-300"
                            value="${subject.id}"
                            ${checkedAttr}
                            onchange="toggleTeacherSubjectSelection('${subject.id}')"
                        >
                        <div class="subject-checkbox-info">
                            <div class="subject-checkbox-code teacher-subject-checkbox-code">${subject.subject_code}</div>
                            <div class="subject-checkbox-name teacher-subject-checkbox-name">${subject.subject_name}</div>
                            <div class="text-xs text-gray-500 mt-1 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1">
                                    <i class="fas fa-graduation-cap text-gray-400"></i>
                                    Grade ${subject.grade_level}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <i class="fas fa-stream text-gray-400"></i>
                                    ${subject.strand || 'No strand'}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;

        // Refresh the "Selected Subjects" panel and count
        updateSelectedTeacherSubjectsUI();

    } catch (error) {
        console.error('Error loading teacher subjects:', error);
        document.getElementById('availableTeacherSubjectsList').innerHTML = `
            <div class="text-center text-red-500 py-8">
                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                <div>Error loading subjects</div>
                <div class="text-sm">${error.message}</div>
            </div>
        `;
    }
}

// Toggle teacher subject selection
function toggleTeacherSubjectSelection(subjectId) {
    if (selectedTeacherSubjects.has(subjectId)) {
        selectedTeacherSubjects.delete(subjectId);
    } else {
        selectedTeacherSubjects.add(subjectId);
    }
    updateSelectedTeacherSubjectsUI();
}

// Select all teacher subjects
function selectAllTeacherSubjects() {
    const checkboxes = document.querySelectorAll('.teacher-subject-checkbox');
    checkboxes.forEach(checkbox => {
        const subjectId = checkbox.value;
        checkbox.checked = true;
        selectedTeacherSubjects.add(subjectId);
    });
    updateSelectedTeacherSubjectsUI();
}

// Deselect all teacher subjects
function deselectAllTeacherSubjects() {
    const checkboxes = document.querySelectorAll('.teacher-subject-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    selectedTeacherSubjects.clear();
    updateSelectedTeacherSubjectsUI();
}

// Update the selected teacher subjects UI
function updateSelectedTeacherSubjectsUI() {
    const selectedSection = document.getElementById('selectedTeacherSubjectsSection');
    const selectedList = document.getElementById('selectedTeacherSubjectsList');
    const selectedCount = document.getElementById('selectedTeacherCount');
    const selectAllCheckbox = document.getElementById('selectAllTeacherSubjects');
    
    // Update count
    selectedCount.textContent = selectedTeacherSubjects.size;
    
    // Show/hide selected section
    if (selectedTeacherSubjects.size > 0) {
        selectedSection.classList.remove('hidden');
    } else {
        selectedSection.classList.add('hidden');
    }
    
    // Update select all checkbox state
    const totalSubjects = document.querySelectorAll('.teacher-subject-checkbox').length;
    selectAllCheckbox.checked = selectedTeacherSubjects.size === totalSubjects && totalSubjects > 0;
    selectAllCheckbox.indeterminate = selectedTeacherSubjects.size > 0 && selectedTeacherSubjects.size < totalSubjects;
    
    // Update selected subjects list
    selectedList.innerHTML = '';
    selectedTeacherSubjects.forEach(subjectId => {
        const subjectElement = document.querySelector(`.teacher-subject-checkbox-item[data-subject-id="${subjectId}"]`);
        if (subjectElement) {
            const code = subjectElement.querySelector('.teacher-subject-checkbox-code').textContent;
            const name = subjectElement.querySelector('.teacher-subject-checkbox-name').textContent;
            
            const selectedItem = document.createElement('div');
            selectedItem.className = 'selected-subject-item';
            selectedItem.innerHTML = `
                <div class="selected-subject-info">
                    <div class="text-sm font-medium">${code}</div>
                    <div class="text-xs text-gray-500">${name}</div>
                </div>
                <button type="button" class="remove-subject-btn" onclick="removeSelectedTeacherSubject('${subjectId}')">
                    <i class="fas fa-times"></i>
                </button>
            `;
            selectedList.appendChild(selectedItem);
        }
    });
}

// Remove a subject from teacher selection
function removeSelectedTeacherSubject(subjectId) {
    selectedTeacherSubjects.delete(subjectId);
    const checkbox = document.querySelector(`.teacher-subject-checkbox[value="${subjectId}"]`);
    if (checkbox) {
        checkbox.checked = false;
    }
    updateSelectedTeacherSubjectsUI();
}

// Load teachers for assignments
async function loadTeachersForAssignments() {
    return loadTeachersForAssignmentForm();
}

// Load teachers into the assignment teacher dropdown (from database)
async function loadTeachersForAssignmentForm() {
    const teacherSelect = $('#assignmentTeacherSelect');
    if (!teacherSelect) return;

    // Show temporary loading text
    teacherSelect.innerHTML = '<option value="">Loading teachers...</option>';

    try {
        // Fetch teachers from teachers.php (same endpoint you use elsewhere)
        const teachers = await apiCall('teachers.php');

        if (!Array.isArray(teachers) || teachers.length === 0) {
            teacherSelect.innerHTML = '<option value="">No teachers found</option>';

            const subjectSelect = $('#assignmentSubjectSelect');
            if (subjectSelect) {
                subjectSelect.disabled = true;
                subjectSelect.innerHTML = '<option value="">Please add teachers first</option>';
            }
            return;
        }

        // Build dropdown options
        teacherSelect.innerHTML = '<option value="">Assign to Teacher</option>' +
            teachers.map(teacher => {
                // teachers.php returns { id, name, strand }
                return `<option value="${teacher.id}">${teacher.name} (${teacher.strand})</option>`;
            }).join('');

        // When teachers are loaded, keep subject select disabled
        // until a teacher is chosen
        const subjectSelect = $('#assignmentSubjectSelect');
        if (subjectSelect) {
            subjectSelect.disabled = true;
            subjectSelect.innerHTML = '<option value="">Please select a teacher first</option>';
        }
    } catch (error) {
        console.error('Error loading teachers for assignments:', error);
        teacherSelect.innerHTML = '<option value="">Error loading teachers</option>';
    }
}

// Remove subject from teacher
async function removeTeacherSubject(assignmentId, teacherId) {
    if (!confirm('Are you sure you want to remove this subject from the teacher?')) return;
    
    try {
        await apiCall(`teacher-subjects.php?id=${assignmentId}`, 'DELETE');
        
        addNotification(
            'warning',
            'Subject Removed',
            'Subject has been removed from teacher'
        );
        
        loadTeacherSubjects(teacherId);
    } catch (error) {
        addNotification(
            'error',
            'Subject Removal Failed',
            `Failed to remove subject: ${error.message}`
        );
    }
}

// Assign subjects to teacher form submission
document.getElementById('assignTeacherSubjectsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    try {
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Validate that at least one subject is selected
        if (selectedTeacherSubjects.size === 0) {
            addNotification('error', 'No Subjects Selected', 'Please select at least one subject to assign');
            return;
        }
        
        // Prepare data for API
        const submitData = {
            teacherId: data.teacherId,
            subjectIds: Array.from(selectedTeacherSubjects)
        };
        
        const result = await apiCall('teacher-subjects.php', 'POST', submitData);
        
        addNotification(
            'success',
            'Subjects Assigned',
            `${selectedTeacherSubjects.size} subjects have been assigned to teacher`
        );
        
        closeAssignTeacherSubjectsModal();
        loadTeacherSubjects(currentTeacherId);
    } catch (error) {
        addNotification(
            'error',
            'Subject Assignment Failed',
            `Failed to assign subjects: ${error.message}`
        );
    }
});

function closeAssignTeacherSubjectsModal() {
    document.getElementById('assignTeacherSubjectsModal').classList.add('hidden');
    document.getElementById('assignTeacherSubjectsForm').reset();
    selectedTeacherSubjects.clear();
    updateSelectedTeacherSubjectsUI();
}

// Event listeners for teacher subject assignment
document.getElementById('closeAssignTeacherSubjectsModal').addEventListener('click', closeAssignTeacherSubjectsModal);
document.getElementById('cancelAssignTeacherSubjects').addEventListener('click', closeAssignTeacherSubjectsModal);
document.getElementById('assignTeacherSubjectsModal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('assignTeacherSubjectsModal')) {
        closeAssignTeacherSubjectsModal();
    }
});

document.getElementById('selectAllTeacherSubjects').addEventListener('change', function() {
    if (this.checked) {
        selectAllTeacherSubjects();
    } else {
        deselectAllTeacherSubjects();
    }
});

// ====== MULTIPLE SUBJECT SELECTION FUNCTIONALITY ======

// Filter visible teacher subjects by strand (front-end only)
function applyTeacherStrandFilter(strandValue) {
    const items = document.querySelectorAll('.teacher-subject-checkbox-item');
    if (!items.length) return;

    const value = strandValue || 'all';
    const normalized = value.toUpperCase();

    items.forEach(item => {
        const itemStrand = (item.getAttribute('data-strand') || '').toUpperCase();

        if (normalized === 'ALL' || !value || itemStrand === normalized) {
            item.classList.remove('hidden');
        } else {
            item.classList.add('hidden');
        }
    });
}

// Hook up the strand dropdown in the Assign Subjects modal
const strandFilterEl = document.getElementById('teacherStrandFilter');
if (strandFilterEl) {
    strandFilterEl.addEventListener('change', function () {
        applyTeacherStrandFilter(this.value);
    });
}

// Toggle subject selection
function toggleSubjectSelection(subjectId) {
    if (selectedSubjects.has(subjectId)) {
        selectedSubjects.delete(subjectId);
    } else {
        selectedSubjects.add(subjectId);
    }
    updateSelectedSubjectsUI();
}

// Select all subjects
function selectAllSubjects() {
    const checkboxes = document.querySelectorAll('.subject-checkbox');
    checkboxes.forEach(checkbox => {
        const subjectId = checkbox.value;
        checkbox.checked = true;
        selectedSubjects.add(subjectId);
    });
    updateSelectedSubjectsUI();
}

// Deselect all subjects
function deselectAllSubjects() {
    const checkboxes = document.querySelectorAll('.subject-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    selectedSubjects.clear();
    updateSelectedSubjectsUI();
}

// Update the selected subjects UI
function updateSelectedSubjectsUI() {
    const selectedSection = document.getElementById('selectedSubjectsSection');
    const selectedList = document.getElementById('selectedSubjectsList');
    const selectedCount = document.getElementById('selectedCount');
    const selectAllCheckbox = document.getElementById('selectAllSubjects');
    
    // Update count
    selectedCount.textContent = selectedSubjects.size;
    
    // Show/hide selected section
    if (selectedSubjects.size > 0) {
        selectedSection.classList.remove('hidden');
    } else {
        selectedSection.classList.add('hidden');
    }
    
    // Update select all checkbox state
    const totalSubjects = document.querySelectorAll('.subject-checkbox').length;
    selectAllCheckbox.checked = selectedSubjects.size === totalSubjects && totalSubjects > 0;
    selectAllCheckbox.indeterminate = selectedSubjects.size > 0 && selectedSubjects.size < totalSubjects;
    
    // Update selected subjects list
    selectedList.innerHTML = '';
    selectedSubjects.forEach(subjectId => {
        const subjectElement = document.querySelector(`.subject-checkbox-item[data-subject-id="${subjectId}"]`);
        if (subjectElement) {
            const code = subjectElement.querySelector('.subject-checkbox-code').textContent;
            const name = subjectElement.querySelector('.subject-checkbox-name').textContent;
            
            const selectedItem = document.createElement('div');
            selectedItem.className = 'selected-subject-item';
            selectedItem.innerHTML = `
                <div class="selected-subject-info">
                    <div class="text-sm font-medium">${code}</div>
                    <div class="text-xs text-gray-500">${name}</div>
                </div>
                <button type="button" class="remove-subject-btn" onclick="removeSelectedSubject('${subjectId}')">
                    <i class="fas fa-times"></i>
                </button>
            `;
            selectedList.appendChild(selectedItem);
        }
    });
}

// Remove a subject from selection
function removeSelectedSubject(subjectId) {
    selectedSubjects.delete(subjectId);
    const checkbox = document.querySelector(`.subject-checkbox[value="${subjectId}"]`);
    if (checkbox) {
        checkbox.checked = false;
    }
    updateSelectedSubjectsUI();
}

// ====== SEARCH FUNCTIONALITY ======
function initializeSearch() {
    const searchStudentInput = document.getElementById('searchStudent');
    const searchTeacherInput = document.getElementById('searchTeacher');
    
    if (searchStudentInput) {
        searchStudentInput.addEventListener('input', debounce(filterStudents, 300));
    }
    
    if (searchTeacherInput) {
        searchTeacherInput.addEventListener('input', debounce(filterTeachers, 300));
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

async function filterStudents() {
    const searchTerm = document.getElementById('searchStudent').value.toLowerCase();
    const strandFilter = document.getElementById('filterStrand').value;
    
    let filteredStudents = allStudents;
    
    // Apply search filter only
    if (searchTerm) {
        filteredStudents = filteredStudents.filter(student => 
            student.full_name.toLowerCase().includes(searchTerm) ||
            student.student_id.toLowerCase().includes(searchTerm) ||
            student.strand.toLowerCase().includes(searchTerm)
        );
    }
    
    renderStudentTable(filteredStudents);
}

async function filterTeachers() {
    const searchTerm = document.getElementById('searchTeacher').value.toLowerCase();
    const strandFilter = document.getElementById('filterTeacherStrand').value;
    
    let filteredTeachers = allTeachers;
    
    // Apply search filter only
    if (searchTerm) {
        filteredTeachers = filteredTeachers.filter(teacher => 
            teacher.name.toLowerCase().includes(searchTerm) ||
            teacher.username.toLowerCase().includes(searchTerm) ||
            teacher.strand.toLowerCase().includes(searchTerm)
        );
    }
    
    renderTeacherTable(filteredTeachers);
}

// ====== FILE UPLOAD FUNCTIONALITY ======
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dynamic section loading
    document.getElementById('studentGradeLevel').addEventListener('change', loadSectionsForStudent);
    document.getElementById('studentStrand').addEventListener('change', loadSectionsForStudent);
    
    // Initialize search functionality
    initializeSearch();
    
    // Material file upload functionality
    const materialFileInput = document.getElementById('materialFileInput');
    const materialFileBrowseButton = document.getElementById('materialFileBrowseButton');
    const materialFileNameDisplay = document.getElementById('materialFileNameDisplay');

    // Assignment file upload functionality
    const assignmentFileInput = document.getElementById('assignmentFileInput');
    const assignmentFileBrowseButton = document.getElementById('assignmentFileBrowseButton');
    const assignmentFileNameDisplay = document.getElementById('assignmentFileNameDisplay');

    // Material file browser setup
    materialFileBrowseButton.addEventListener('click', function() {
        materialFileInput.click();
    });

    materialFileInput.addEventListener('change', function() {
        handleFileSelection(materialFileInput, materialFileNameDisplay, 200); // 200MB limit for materials
    });

    // Assignment file browser setup
    assignmentFileBrowseButton.addEventListener('click', function() {
        assignmentFileInput.click();
    });

    assignmentFileInput.addEventListener('change', function() {
        handleFileSelection(assignmentFileInput, assignmentFileNameDisplay, 200); // 200MB limit for assignments
    });

    // Handle file selection with validation
    function handleFileSelection(fileInput, fileNameDisplay, maxSizeMB) {
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            fileNameDisplay.textContent = file.name;
            fileNameDisplay.classList.add('has-file');
            
            // Validate file size
            const maxSize = maxSizeMB * 1024 * 1024; // Convert MB to bytes
            if (file.size > maxSize) {
                addNotification(
                    'error',
                    'File Too Large',
                    `File size exceeds ${maxSizeMB}MB limit. Please choose a smaller file.`
                );
                fileInput.value = '';
                fileNameDisplay.textContent = 'No file selected';
                fileNameDisplay.classList.remove('has-file');
                return;
            }
            
            // Validate file type
            const allowedTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'application/zip',
                'video/mp4',
                'audio/mpeg',
                'image/jpeg',
                'image/jpg',
                'image/png'
            ];
            
            if (!allowedTypes.includes(file.type) && !file.name.match(/\.(pdf|doc|docx|ppt|pptx|txt|zip|mp4|mp3|jpg|jpeg|png)$/i)) {
                addNotification(
                    'error',
                    'Invalid File Type',
                    'Please select a supported file type (PDF, DOC, PPT, MP4, MP3, Images, ZIP).'
                );
                fileInput.value = '';
                fileNameDisplay.textContent = 'No file selected';
                fileNameDisplay.classList.remove('has-file');
                return;
            }
            
            addNotification(
                'success',
                'File Selected',
                `"${file.name}" is ready for upload (${formatFileSize(file.size)})`
            );
        } else {
            fileNameDisplay.textContent = 'No file selected';
            fileNameDisplay.classList.remove('has-file');
        }
    }
});

// Helper function to format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ====== NOTIFICATION SYSTEM ======
let notifications = {
    active: [],
    archive: [],
    unreadCount: 0
};

// Load notifications from localStorage
function loadNotifications() {
    const saved = localStorage.getItem('admin_notifications');
    if (saved) {
        const data = JSON.parse(saved);
        notifications.active = data.active || [];
        notifications.archive = data.archive || [];
        notifications.unreadCount = data.unreadCount || 0;
    }
    updateNotificationUI();
}

// Save notifications to localStorage
function saveNotifications() {
    localStorage.setItem('admin_notifications', JSON.stringify(notifications));
}

// Add a new notification
function addNotification(type, title, message, data = {}) {
    const notification = {
        id: Date.now(),
        type: type, // 'success', 'warning', 'error', 'info'
        title: title,
        message: message,
        data: data,
        timestamp: new Date().toISOString(),
        read: false
    };

    notifications.active.unshift(notification);
    notifications.unreadCount++;
    
    // Show toast notification
    showToastNotification(notification);
    
    updateNotificationUI();
    saveNotifications();
}

// Show toast notification
function showToastNotification(notification) {
    const toast = document.createElement('div');
    toast.className = `fixed top-20 right-4 z-50 max-w-sm rounded-lg border-l-4 p-4 shadow-lg bg-white dark:bg-gray-800 notification-item notification-${notification.type}`;
    
    let icon = 'ℹ️';
    let bgColor = 'bg-blue-50 dark:bg-blue-900/20';
    
    switch(notification.type) {
        case 'success':
            icon = '✅';
            bgColor = 'bg-green-50 dark:bg-green-900/20';
            break;
        case 'warning':
            icon = '⚠️';
            bgColor = 'bg-yellow-50 dark:bg-yellow-900/20';
            break;
        case 'error':
            icon = '❌';
            bgColor = 'bg-red-50 dark:bg-red-900/20';
            break;
    }
    
    toast.innerHTML = `
        <div class="flex items-start gap-3">
            <span class="text-lg">${icon}</span>
            <div class="flex-1">
                <div class="font-medium text-sm">${notification.title}</div>
                <div class="text-xs text-gray-600 dark:text-gray-300 mt-1">${notification.message}</div>
                <div class="text-xs text-gray-500 mt-2">${new Date(notification.timestamp).toLocaleTimeString()}</div>
            </div>
            <button class="text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// Update notification UI
function updateNotificationUI() {
    const countElement = document.getElementById('notificationCount');
    const listElement = document.getElementById('notificationList');
    const archiveElement = document.getElementById('notificationArchive');
    
    // Update badge count
    if (notifications.unreadCount > 0) {
        countElement.textContent = notifications.unreadCount > 99 ? '99+' : notifications.unreadCount;
        countElement.classList.remove('hidden');
    } else {
        countElement.classList.add('hidden');
    }
    
    // Update notification list
    if (notifications.active.length === 0) {
        listElement.innerHTML = '<div class="text-center text-gray-500 py-4 text-sm">No new notifications</div>';
    } else {
        listElement.innerHTML = notifications.active.map(notification => `
            <div class="notification-item p-3 rounded-lg border border-gray-200 dark:border-gray-700 mb-2 notification-${notification.type} ${!notification.read ? 'bg-blue-50 dark:bg-blue-900/20' : ''}">
                <div class="flex items-start gap-3">
                    <div class="flex-1">
                        <div class="font-medium text-sm">${notification.title}</div>
                        <div class="text-xs text-gray-600 dark:text-gray-300 mt-1">${notification.message}</div>
                        <div class="text-xs text-gray-500 mt-2">${new Date(notification.timestamp).toLocaleString()}</div>
                    </div>
                    <button class="text-gray-400 hover:text-gray-600" onclick="archiveNotification(${notification.id})">
                        <i class="fas fa-archive"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    // Update archive
    if (notifications.archive.length === 0) {
        archiveElement.innerHTML = '<div class="text-center text-gray-500 py-8">No archived notifications</div>';
    } else {
        archiveElement.innerHTML = notifications.archive.map(notification => `
            <div class="notification-archive-item p-3 rounded-lg border border-gray-200 dark:border-gray-700 mb-2 notification-${notification.type}">
                <div class="flex items-start gap-3">
                    <div class="flex-1">
                        <div class="font-medium text-sm">${notification.title}</div>
                        <div class="text-xs text-gray-600 dark:text-gray-300 mt-1">${notification.message}</div>
                        <div class="text-xs text-gray-500 mt-2">${new Date(notification.timestamp).toLocaleString()}</div>
                    </div>
                </div>
            </div>
        `).join('');
    }
}

// Archive a notification
function archiveNotification(id) {
    const index = notifications.active.findIndex(n => n.id === id);
    if (index !== -1) {
        const [notification] = notifications.active.splice(index, 1);
        notification.read = true;
        notifications.archive.unshift(notification);
        if (!notification.read) {
            notifications.unreadCount = Math.max(0, notifications.unreadCount - 1);
        }
        updateNotificationUI();
        saveNotifications();
    }
}

// Clear all notifications
function clearNotifications() {
    notifications.active.forEach(notification => {
        notification.read = true;
        notifications.archive.unshift(notification);
    });
    notifications.active = [];
    notifications.unreadCount = 0;
    updateNotificationUI();
    saveNotifications();
}

// Clear archive
function clearArchive() {
    notifications.archive = [];
    updateNotificationUI();
    saveNotifications();
}

// ====== UTILITIES ======
const $ = (sel, el=document) => el.querySelector(sel);
const $$ = (sel, el=document) => Array.from(el.querySelectorAll(sel));
const fmt = (d) => new Date(d).toLocaleString();

// Get quarter badge class
function getQuarterBadgeClass(quarter) {
    return `quarter-badge quarter-${quarter}`;
}

// Get quarter display name
function getQuarterDisplayName(quarter) {
    const names = { '1': '1st Quarter', '2': '2nd Quarter', '3': '3rd Quarter', '4': '4th Quarter' };
    return names[quarter] || quarter;
}

// Get strand CSS class
function getStrandClass(strand) {
    const strandMap = {
        'HUMSS': 'strand-humss',
        'ICT': 'strand-ict',
        'STEM': 'strand-stem',
        'TVL': 'strand-tvl',
        'TVL-HE': 'strand-tvl-he'
    };
    return strandMap[strand] || '';
}

// Password visibility state
let passwordVisibility = {};

// Toggle password visibility
function togglePasswordVisibility(teacherId) {
    const displayElement = document.getElementById('passwordDisplay');
    const toggleButton = document.getElementById('togglePasswordBtn');
    
    if (passwordVisibility[teacherId]) {
        // Hide password
        displayElement.textContent = '••••••••';
        toggleButton.innerHTML = '<i class="fas fa-eye"></i> Show';
        passwordVisibility[teacherId] = false;
    } else {
        // Show password - in a real system, you'd need to get this from the server
        // For demo purposes, we'll show a placeholder
        displayElement.textContent = '********';
        toggleButton.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
        passwordVisibility[teacherId] = true;
        
        // Show a notification that in production this would show the actual password
        addNotification(
            'info',
            'Password Display',
            'In a production system, this would show the actual password. For security, passwords are stored as hashes and cannot be retrieved.'
        );
    }
}

// API call function - FIXED VERSION
async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
        },
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(API_BASE + endpoint, options);
        const responseText = await response.text(); // Get raw response first
        
        console.log('API Response for', endpoint, ':', responseText); // Debug log
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Raw response:', responseText);
            throw new Error('Server returned invalid JSON. Check API endpoint: ' + endpoint);
        }
        
        if (!response.ok) {
            throw new Error(result.message || `API request failed with status ${response.status}`);
        }
        
        return result;
    } catch (error) {
        console.error('API call failed:', error);
        throw error;
    }
}

// ====== MODAL FUNCTIONS ======
function openEditModal(student) {
    const modal = $('#editStudentModal');
    
    // Populate form with student data
    $('#editStudentId').value = student.id;
    $('#editStudentIdField').value = student.student_id;
    
    // Parse name into components
    const nameParts = student.full_name.split(' ');
    let firstName = '', middleName = '', lastName = '';
    
    if (nameParts.length === 1) {
        firstName = nameParts[0];
    } else if (nameParts.length === 2) {
        firstName = nameParts[0];
        lastName = nameParts[1];
    } else {
        firstName = nameParts[0];
        lastName = nameParts[nameParts.length - 1];
        middleName = nameParts.slice(1, -1).join(' ');
    }
    
    $('#editFirstName').value = firstName;
    $('#editMiddleName').value = middleName;
    $('#editLastName').value = lastName;
    $('#editUsername').value = student.username || '';
    $('#editEmail').value = student.email || '';
    $('#editGradeLevel').value = student.grade_level;
    $('#editStrand').value = student.strand;
    
    // Set irregular student status
    if (student.is_irregular) {
        $('#editIrregularStudent').checked = true;
    } else {
        $('#editRegularStudent').checked = true;
    }
    
    // Load sections for edit modal
    loadSectionsForEditModal(student.grade_level, student.strand, student.section);
    
    modal.classList.remove('hidden');
}

async function loadSectionsForEditModal(gradeLevel, strand, currentSection) {
    const sectionSelect = $('#editSection');
    
    try {
        const sections = await apiCall(`sections.php?gradeLevel=${gradeLevel}&strand=${strand}`);
        
        if (sections.length > 0) {
            sectionSelect.innerHTML = '<option value="">Select Section</option>' +
                sections.map(section => 
                    `<option value="${section.name}" ${section.name === currentSection ? 'selected' : ''}>${section.name}</option>`
                ).join('');
        } else {
            sectionSelect.innerHTML = '<option value="">No sections available</option>';
        }
    } catch (error) {
        console.error('Error loading sections for edit:', error);
        sectionSelect.innerHTML = '<option value="">Error loading sections</option>';
    }
}

function closeEditModal() {
    $('#editStudentModal').classList.add('hidden');
}

// Modal event listeners
$('#closeEditModal').addEventListener('click', closeEditModal);
$('#cancelEdit').addEventListener('click', closeEditModal);
$('#editStudentModal').addEventListener('click', (e) => {
    if (e.target === $('#editStudentModal')) {
        closeEditModal();
    }
});

// ====== STUDENT SUBJECTS FUNCTIONALITY ======

// Load student subjects with course card display
async function loadStudentSubjects(studentId) {
    try {
        const subjects = await apiCall(`student-subjects.php?studentId=${studentId}`);
        const container = $('#studentSubjectsList');
        
        if (subjects.length === 0) {
            container.innerHTML = `
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-book-open text-4xl mb-4 text-gray-400"></i>
                    <div class="text-lg font-medium text-gray-500 mb-2">No subjects assigned</div>
                    <div class="text-sm text-gray-400 mb-4">This student may be irregular - add subjects manually</div>
                    <button onclick="openAddSubjectModal(${studentId})" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Subjects
                    </button>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    ${subjects.map(subject => `
                        <div class="course-progress border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all duration-300 bg-white">
                            <div class="h-24 rounded-lg mb-3 flex items-center justify-center relative" style="background-color: ${subject.color || '#DBEAFE'}">
                                <i class="fas fa-${subject.icon || 'book'} text-3xl" style="color: ${subject.color || '#2563EB'}"></i>
                                <span class="absolute top-2 right-2 ${getQuarterBadgeClass(subject.quarter)}">
                                    Q${subject.quarter}
                                </span>
                            </div>
                            <h3 class="font-bold text-gray-800 text-sm mb-1">${subject.display_subject_name || subject.subject_name}</h3>
                            <p class="text-xs text-gray-500 mb-2">${subject.display_subject_code || subject.subject_code}</p>
                            
                            <div class="mt-2 space-y-2 text-xs">
                                ${subject.teacher_name ? `
                                    <div class="flex items-center text-gray-600">
                                        <i class="fas fa-user mr-2"></i>
                                        <span>${subject.teacher_name}</span>
                                    </div>
                                ` : ''}
                                
                                ${subject.schedule ? `
                                    <div class="flex items-center text-gray-600">
                                        <i class="fas fa-clock mr-2"></i>
                                        <span>${subject.schedule}</span>
                                    </div>
                                ` : ''}
                                
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-graduation-cap mr-2"></i>
                                    <span>${subject.credits || 1} credit${subject.credits > 1 ? 's' : ''}</span>
                                </div>
                            </div>
                            
                            <div class="mt-3 flex justify-between items-center">
                                <span class="text-xs text-gray-500">
                                    Added: ${new Date(subject.created_at).toLocaleDateString()}
                                </span>
                                <button onclick="removeSubject(${subject.id}, ${studentId})" 
                                        class="text-red-600 hover:text-red-800 text-xs font-medium">
                                    <i class="fas fa-trash mr-1"></i>Remove
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading student subjects:', error);
        $('#studentSubjectsList').innerHTML = `
            <div class="text-center text-red-500 py-8">
                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                <div>Error loading subjects</div>
                <div class="text-sm">${error.message}</div>
            </div>
        `;
    }
}

// Enhanced Add Subject Modal with multiple subject selection
async function openAddSubjectModal(studentId) {
    const modal = $('#addSubjectModal');
    
    // Reset selected subjects
    selectedSubjects.clear();
    
    // Populate form with student ID
    $('#addSubjectStudentId').value = studentId;
    
    // Load available subjects for this student's strand and ALL grade levels for ICT
    await loadAvailableSubjects(studentId);
    
    // Load teachers for dropdown
    await loadTeachersForSubjectModal();
    
    modal.classList.remove('hidden');
}

// Load available subjects based on student's strand and ALL grade levels for ICT
async function loadAvailableSubjects(studentId) {
    try {
        // Get student info first
        const student = await apiCall(`students.php?id=${studentId}`);
        
        // For ICT students, load subjects from ALL grade levels
        // For other strands, load only the student's current grade level
        let endpoint;
        if (student.strand === 'ICT') {
            endpoint = `subjects.php?strand=${student.strand}`; // No grade level filter for ICT
        } else {
            endpoint = `subjects.php?strand=${student.strand}&gradeLevel=${student.grade_level}`;
        }
        
        const subjects = await apiCall(endpoint);
        
        const container = $('#availableSubjectsList');
        
        if (subjects.length === 0) {
            container.innerHTML = `
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-book-open text-2xl mb-2"></i>
                    <div>No subjects available for ${student.strand}</div>
                    <div class="text-sm text-gray-400 mt-2">Please add subjects to the system first</div>
                </div>
            `;
            return;
        }
        
        // Group subjects by grade level for better organization
        const subjectsByGrade = {};
        subjects.forEach(subject => {
            const gradeLevel = subject.grade_level;
            if (!subjectsByGrade[gradeLevel]) {
                subjectsByGrade[gradeLevel] = [];
            }
            subjectsByGrade[gradeLevel].push(subject);
        });
        
        let html = '';
        
        // If it's ICT and we have multiple grade levels, show them grouped
        if (student.strand === 'ICT' && Object.keys(subjectsByGrade).length > 1) {
            Object.keys(subjectsByGrade).sort().forEach(gradeLevel => {
                const gradeSubjects = subjectsByGrade[gradeLevel];
                html += `
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2 p-2 bg-gray-50 rounded-lg">
                            <i class="fas fa-graduation-cap text-blue-600"></i>
                            <h4 class="font-semibold text-sm text-gray-700">Grade ${gradeLevel} Subjects</h4>
                            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                                ${gradeSubjects.length} subjects
                            </span>
                        </div>
                        <div class="space-y-2">
                `;
                
                gradeSubjects.forEach(subject => {
                    html += `
                        <div class="subject-checkbox-item" data-subject-id="${subject.id}">
                            <input type="checkbox" 
                                   class="subject-checkbox rounded border-gray-300" 
                                   value="${subject.id}" 
                                   onchange="toggleSubjectSelection('${subject.id}')">
                            <div class="subject-checkbox-info">
                                <div class="subject-checkbox-code">${subject.subject_code}</div>
                                <div class="subject-checkbox-name">${subject.subject_name}</div>
                                ${student.strand === 'ICT' ? `
                                    <div class="text-xs text-gray-500 mt-1">
                                        <span class="inline-flex items-center gap-1">
                                            <i class="fas fa-graduation-cap text-gray-400"></i>
                                            Grade ${subject.grade_level}
                                        </span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            });
        } else {
            // Single grade level or non-ICT strand - show simple list
            subjects.forEach(subject => {
                html += `
                    <div class="subject-checkbox-item" data-subject-id="${subject.id}">
                        <input type="checkbox" 
                               class="subject-checkbox rounded border-gray-300" 
                               value="${subject.id}" 
                               onchange="toggleSubjectSelection('${subject.id}')">
                        <div class="subject-checkbox-info">
                            <div class="subject-checkbox-code">${subject.subject_code}</div>
                            <div class="subject-checkbox-name">${subject.subject_name}</div>
                            ${student.strand === 'ICT' ? `
                                <div class="text-xs text-gray-500 mt-1">
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fas fa-graduation-cap text-gray-400"></i>
                                        Grade ${subject.grade_level}
                                    </span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
        }
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading available subjects:', error);
        $('#availableSubjectsList').innerHTML = `
            <div class="text-center text-red-500 py-8">
                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                <div>Error loading subjects</div>
                <div class="text-sm">${error.message}</div>
            </div>
        `;
    }
}

// Enhanced Add Subject Form with multiple subject support
$('#addSubjectForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    try {
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Validate that at least one subject is selected
        if (selectedSubjects.size === 0) {
            addNotification('error', 'No Subjects Selected', 'Please select at least one subject to add');
            return;
        }
        
        // Prepare data for API - multiple subjects
        const submitData = {
            studentId: data.studentId,
            quarter: data.quarter,
            credits: data.credits,
            teacherId: data.teacherId || null,
            schedule: data.schedule || '',
            subjectIds: Array.from(selectedSubjects)
        };
        
        console.log('Submitting multiple subjects:', submitData);
        
        const result = await apiCall('student-subjects.php', 'POST', submitData);
        
        addNotification(
            'success',
            'Subjects Added',
            `${selectedSubjects.size} subjects have been added to student`
        );
        
        closeAddSubjectModal();
        loadStudentSubjects(currentStudentId);
    } catch (error) {
        addNotification(
            'error',
            'Subject Addition Failed',
            `Failed to add subjects: ${error.message}`
        );
    }
});

// Remove subject from student
async function removeSubject(subjectId, studentId) {
    if (!confirm('Are you sure you want to remove this subject from the student?')) return;
    
    try {
        await apiCall(`student-subjects.php?id=${subjectId}`, 'DELETE');
        
        addNotification(
            'warning',
            'Subject Removed',
            'Subject has been removed from student'
        );
        
        loadStudentSubjects(studentId);
    } catch (error) {
        addNotification(
            'error',
            'Subject Removal Failed',
            `Failed to remove subject: ${error.message}`
        );
    }
}

// Load teachers for subject modal
async function loadTeachersForSubjectModal() {
    try {
        const teachers = await apiCall('teachers.php');
        const select = $('#subjectTeacher');
        
        select.innerHTML = '<option value="">Select Teacher (Optional)</option>' +
            teachers.map(teacher => {
                return `<option value="${teacher.id}">${teacher.name} (${teacher.strand})</option>`;
            }).join('');
    } catch (error) {
        console.error('Error loading teachers for subject modal:', error);
    }
}

function closeAddSubjectModal() {
    $('#addSubjectModal').classList.add('hidden');
    $('#addSubjectForm').reset();
    selectedSubjects.clear();
    updateSelectedSubjectsUI();
}

// Add subject modal event listeners
$('#closeAddSubjectModal').addEventListener('click', closeAddSubjectModal);
$('#cancelAddSubject').addEventListener('click', closeAddSubjectModal);
$('#addSubjectModal').addEventListener('click', (e) => {
    if (e.target === $('#addSubjectModal')) {
        closeAddSubjectModal();
    }
});

// Select all checkbox event listener
document.getElementById('selectAllSubjects').addEventListener('change', function() {
    if (this.checked) {
        selectAllSubjects();
    } else {
        deselectAllSubjects();
    }
});

// Add subject button handler
$('#btnAddSubject').addEventListener('click', () => {
    if (currentStudentId) {
        openAddSubjectModal(currentStudentId);
    }
});

// ====== TEACHER MODAL FUNCTIONS ======
function openEditTeacherModal(teacher) {
    const modal = $('#editTeacherModal');
    
    // Parse the name into components
    const nameParts = teacher.name.split(' ');
    let firstName = '', middleName = '', lastName = '';
    
    if (nameParts.length === 1) {
        firstName = nameParts[0];
    } else if (nameParts.length === 2) {
        firstName = nameParts[0];
        lastName = nameParts[1];
    } else {
        firstName = nameParts[0];
        lastName = nameParts[nameParts.length - 1];
        middleName = nameParts.slice(1, -1).join(' ');
    }
    
    // Populate form with teacher data
    $('#editTeacherId').value = teacher.id;
    $('#editTeacherFirstName').value = firstName;
    $('#editTeacherMiddleName').value = middleName;
    $('#editTeacherLastName').value = lastName;
    $('#editTeacherEmail').value = teacher.email || '';
    $('#editTeacherUsername').value = teacher.username || '';
    $('#editTeacherStrand').value = teacher.strand;
    
    modal.classList.remove('hidden');
}

function closeEditTeacherModal() {
    $('#editTeacherModal').classList.add('hidden');
}

// Teacher modal event listeners
$('#closeEditTeacherModal').addEventListener('click', closeEditTeacherModal);
$('#cancelEditTeacher').addEventListener('click', closeEditTeacherModal);
$('#editTeacherModal').addEventListener('click', (e) => {
    if (e.target === $('#editTeacherModal')) {
        closeEditTeacherModal();
    }
});

// ====== NAVIGATION ======
const sections = {
    dashboard: $('#section-dashboard'),
    students: $('#section-students'),
    materials: $('#section-materials'),
    teachers: $('#section-teachers'),
    distribution: $('#section-distribution'),
    'student-support': $('#section-student-support'),
    about: $('#section-about'),
    notifications: $('#section-notifications')
};

$$('#sidebar .nav-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
        $$('#sidebar .nav-btn').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        const id = btn.dataset.section;
        Object.entries(sections).forEach(([k,sec])=>{
            sec.classList.toggle('hidden', k!==id);
        });
        closeSidebar();
        if(id==='dashboard') renderDashboard();
        if(id==='students') { renderStudents(); renderStudentTable(); }
        if(id==='materials') { renderMaterials(); loadMaterials(); loadAssignments(); loadTeachersForAssignments(); }
        if(id==='teachers') { renderTeachers(); renderTeacherTable(); loadTeacherWorkload(); }
        if(id==='distribution') { renderDistribution(); loadQuarterlyStats(); }
        if(id==='student-support') { renderStudentSupport(); }
    })
})

// Sidebar (mobile) - FIXED
const sidebar = $('#sidebar');
const backdrop = $('#backdrop');
$('#btnOpenSidebar').addEventListener('click',()=>{
    sidebar.classList.remove('hidden');
    sidebar.classList.add('mobile-open');
    backdrop.classList.remove('hidden');
});
function closeSidebar(){ 
    sidebar.classList.remove('mobile-open');
    backdrop.classList.add('hidden'); 
}
backdrop.addEventListener('click', closeSidebar);

// ====== DASHBOARD RENDER ======
async function renderDashboard(){
    try {
        // ===== Stats for total + strands =====
        const stats = await apiCall('stats.php');
        
        $('#statTotal').textContent   = stats.total   || 0;
        $('#statHUMSS').textContent   = stats.HUMSS   || 0;
        $('#statICT').textContent     = stats.ICT     || 0;
        $('#statSTEM').textContent    = stats.STEM    || 0;
        $('#statTVL').textContent     = stats.TVL     || 0;
        $('#statTVLHE').textContent   = stats['TVL-HE'] || 0;

        // ===== Quarter statistics - WITH ERROR HANDLING =====
        try {
            const quarterStats = await apiCall('stats/quarters.php');
            $('#statQ1').textContent = `${quarterStats.q1?.materials || 0} Materials`;
            $('#statQ2').textContent = `${quarterStats.q2?.materials || 0} Materials`;
            $('#statQ3').textContent = `${quarterStats.q3?.materials || 0} Materials`;
            $('#statQ4').textContent = `${quarterStats.q4?.materials || 0} Materials`;
        } catch (quarterError) {
            console.warn('Quarter stats not available:', quarterError);
            // Set default values if quarter stats fail
            $('#statQ1').textContent = '0 Materials';
            $('#statQ2').textContent = '0 Materials';
            $('#statQ3').textContent = '0 Materials';
            $('#statQ4').textContent = '0 Materials';
        }

        // ===== Recent students - FIXED VERSION =====
        try {
            const allStudents = await apiCall('students.php');
            const recentStudents = Array.isArray(allStudents) ? allStudents : [];
            
            // Sort by creation date (newest first)
            const sortedStudents = [...recentStudents]
                .sort((a, b) => {
                    if (a.created_at && b.created_at) {
                        return new Date(b.created_at) - new Date(a.created_at);
                    }
                    return (b.id || 0) - (a.id || 0);
                })
                .slice(0, 8);

            const tbody = $('#recentStudents');
            
            if (sortedStudents.length > 0) {
                tbody.innerHTML = sortedStudents.map(student => `
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap text-xs">
                            ${student.created_at ? new Date(student.created_at).toLocaleDateString() : '—'}
                        </td>
                        <td class="px-3 py-2 text-xs">${student.full_name || 'Unknown'}</td>
                        <td class="px-3 py-2 text-xs">${student.strand || '—'}</td>
                        <td class="px-3 py-2 text-xs">${student.student_id || '—'}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td class="px-3 py-2 text-center text-gray-500 text-xs" colspan="4">No students found</td></tr>';
            }
        } catch (studentError) {
            console.error('Error loading recent students:', studentError);
            $('#recentStudents').innerHTML = `
                <tr>
                    <td class="px-3 py-2 text-center text-red-500 text-xs" colspan="4">
                        Error loading students
                    </td>
                </tr>
            `;
        }

        // ===== UPCOMING SCHOOL EVENTS & DEADLINES - SEPARATED FROM NOTIFICATIONS =====
        await loadSchoolEvents();

    } catch (error) {
        console.error('Error loading dashboard:', error);
        addNotification('error', 'Dashboard Error', 'Failed to load dashboard data');
    }
}

// ====== SCHOOL EVENTS & DEADLINES ======
async function loadSchoolEvents() {
    try {
        // Try to get events from API first
        let events = [];
        try {
            events = await apiCall('events.php');
        } catch (apiError) {
            console.warn('Events API not available, using sample data');
            // Use sample school events data
            events = getSampleSchoolEvents();
        }

        const ul = $('#upcomingDeadlines');
        
        if (events && events.length > 0) {
            // Sort events by date (soonest first)
            const sortedEvents = events
                .filter(event => new Date(event.date) >= new Date()) // Only future events
                .sort((a, b) => new Date(a.date) - new Date(b.date))
                .slice(0, 5); // Show next 5 events

            if (sortedEvents.length > 0) {
                ul.innerHTML = sortedEvents.map(event => {
                    const eventDate = new Date(event.date);
                    const isToday = eventDate.toDateString() === new Date().toDateString();
                    const isTomorrow = new Date(eventDate.getTime() - 24 * 60 * 60 * 1000).toDateString() === new Date().toDateString();
                    
                    let dateBadge = '';
                    if (isToday) {
                        dateBadge = '<span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">Today</span>';
                    } else if (isTomorrow) {
                        dateBadge = '<span class="text-xs bg-orange-100 text-orange-800 px-2 py-1 rounded">Tomorrow</span>';
                    }

                    return `
                    <li class="flex items-start gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center ${getEventTypeColor(event.type)}">
                                <i class="fas ${getEventIcon(event.type)} text-white text-sm"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="font-medium text-sm">${event.title}</div>
                                ${dateBadge}
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-300 mb-1">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                ${eventDate.toLocaleDateString()} • ${event.time || 'All Day'}
                            </div>
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                ${event.location || 'School Campus'}
                                ${event.type ? `• ${event.type}` : ''}
                            </div>
                            ${event.description ? `
                                <div class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                    ${event.description}
                                </div>
                            ` : ''}
                        </div>
                    </li>`;

                }).join('');
            } else {
                ul.innerHTML = `
                    <li class="text-center text-gray-500 py-6">
                        <i class="fas fa-calendar-times text-2xl mb-2 text-gray-400"></i>
                        <div class="text-sm">No upcoming events</div>
                        <div class="text-xs text-gray-400 mt-1">Check back later for school events</div>
                    </li>
                `;
            }
        } else {
            ul.innerHTML = `
                <li class="text-center text-gray-500 py-6">
                    <i class="fas fa-calendar-plus text-2xl mb-2 text-gray-400"></i>
                    <div class="text-sm">No events scheduled</div>
                    <div class="text-xs text-gray-400 mt-1">Add school events to see them here</div>
                </li>
            `;
        }
    } catch (error) {
        console.error('Error loading school events:', error);
        $('#upcomingDeadlines').innerHTML = `
            <li class="text-center text-red-500 py-6">
                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                <div class="text-sm">Error loading events</div>
                <div class="text-xs">Please try refreshing the page</div>
            </li>
        `;
    }
}

// ====== EVENT HELPER FUNCTIONS ======
function getEventTypeColor(eventType) {
    const colors = {
        'academic': 'bg-blue-500',
        'holiday': 'bg-green-500',
        'exam': 'bg-red-500',
        'sports': 'bg-orange-500',
        'cultural': 'bg-purple-500',
        'meeting': 'bg-indigo-500',
        'deadline': 'bg-pink-500',
        'default': 'bg-gray-500'
    };
    return colors[eventType?.toLowerCase()] || colors.default;
}

function getEventIcon(eventType) {
    const icons = {
        'academic': 'fa-graduation-cap',
        'holiday': 'fa-umbrella-beach',
        'exam': 'fa-file-alt',
        'sports': 'fa-running',
        'cultural': 'fa-music',
        'meeting': 'fa-users',
        'deadline': 'fa-flag-checkered',
        'default': 'fa-calendar'
    };
    return icons[eventType?.toLowerCase()] || icons.default;
}

// ====== SAMPLE SCHOOL EVENTS DATA ======
function getSampleSchoolEvents() {
    const today = new Date();
    const nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
    const twoWeeks = new Date(today.getTime() + 14 * 24 * 60 * 60 * 1000);
    
    return [
        {
            id: 1,
            title: "Quarter 1 Final Examinations",
            type: "exam",
            date: nextWeek.toISOString().split('T')[0],
            time: "8:00 AM - 12:00 PM",
            location: "Various Classrooms",
            description: "All subjects final exams for Quarter 1"
        },
        {
            id: 2,
            title: "Teachers' In-Service Training",
            type: "meeting",
            date: today.toISOString().split('T')[0],
            time: "1:00 PM - 4:00 PM",
            location: "School Auditorium",
            description: "Quarter 2 curriculum planning and training session"
        },
        {
            id: 3,
            title: "Science Fair 2024",
            type: "academic",
            date: twoWeeks.toISOString().split('T')[0],
            time: "9:00 AM - 3:00 PM",
            location: "School Gymnasium",
            description: "Annual science fair showcasing student projects"
        },
        {
            id: 4,
            title: "Basketball Intramurals",
            type: "sports",
            date: new Date(today.getTime() + 3 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
            time: "2:00 PM - 5:00 PM",
            location: "School Court",
            description: "Strand vs Strand basketball competition"
        },
        {
            id: 5,
            title: "Project Submission Deadline",
            type: "deadline",
            date: new Date(today.getTime() + 2 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
            time: "11:59 PM",
            location: "Online Portal",
            description: "Final deadline for all Quarter 1 projects"
        }
    ];
}

// ====== QUARTERLY STATISTICS FALLBACK ======
async function loadQuarterlyStats() {
    try {
        const stats = await apiCall('stats/quarters.php');
        
        // Update distribution section
        $('#q1Materials').textContent = stats.q1?.materials || 0;
        $('#q1Assignments').textContent = stats.q1?.assignments || 0;
        $('#q1Performance').textContent = stats.q1?.performance_tasks || 0;
        
        $('#q2Materials').textContent = stats.q2?.materials || 0;
        $('#q2Assignments').textContent = stats.q2?.assignments || 0;
        $('#q2Performance').textContent = stats.q2?.performance_tasks || 0;
        
        $('#q3Materials').textContent = stats.q3?.materials || 0;
        $('#q3Assignments').textContent = stats.q3?.assignments || 0;
        $('#q3Performance').textContent = stats.q3?.performance_tasks || 0;
        
        $('#q4Materials').textContent = stats.q4?.materials || 0;
        $('#q4Assignments').textContent = stats.q4?.assignments || 0;
        $('#q4Performance').textContent = stats.q4?.performance_tasks || 0;
    } catch (error) {
        console.warn('Quarterly stats not available, using defaults');
        // Set default values
        ['q1', 'q2', 'q3', 'q4'].forEach(q => {
            $(`#${q}Materials`).textContent = '0';
            $(`#${q}Assignments`).textContent = '0';
            $(`#${q}Performance`).textContent = '0';
        });
    }
}

// ====== STUDENTS CRUD ======
$('#studentForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Build full name from components
        data.fullName = `${data.firstName} ${data.middleName ? data.middleName + ' ' : ''}${data.lastName}`.trim();
        
        await apiCall('students.php', 'POST', data);
        e.target.reset();
        
        // Add notification
        addNotification(
            'success', 
            'Student Added', 
            `Student ${data.fullName} (${data.studentId}) has been added to ${data.strand}`,
            { studentId: data.studentId, strand: data.strand }
        );
        
        renderDashboard();
        renderStudentTable();
    } catch (error) {
        addNotification(
            'error',
            'Student Creation Failed',
            `Failed to add student: ${error.message}`
        );
    }
});

// Edit student form submission
$('#editStudentForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Build full name from components
        data.fullName = `${data.firstName} ${data.middleName ? data.middleName + ' ' : ''}${data.lastName}`.trim();
        
        await apiCall(`students.php?id=${data.id}`, 'PUT', data);
        
        addNotification(
            'success',
            'Student Updated',
            `Student ${data.fullName} has been updated successfully`
        );
        
        closeEditModal();
        renderStudentTable();
        renderDashboard();
    } catch (error) {
        addNotification(
            'error',
            'Update Failed',
            `Failed to update student: ${error.message}`
        );
    }
});

async function renderStudents(){
    $('#filterStrand').value = 'ALL';
    $('#searchStudent').value = '';
    await renderStudentTable();
}

// ====== FIXED STUDENT TABLE RENDERING ======
async function renderStudentTable(studentsToRender = null){
    try {
        const filter = $('#filterStrand').value;
        const searchTerm = $('#searchStudent').value.toLowerCase();
        let items;
        
        if (studentsToRender) {
            // If we have filtered students from search, use them
            items = studentsToRender;
        } else {
            // Otherwise, fetch all students from API
            items = await apiCall('students.php');
            allStudents = items; // Store for search functionality
        }
        
        // Apply strand filter if needed (only when not searching)
        if (filter !== 'ALL' && !searchTerm) {
            items = items.filter(student => student.strand === filter);
        }
        
        const tbody = $('#studentsTable');
        tbody.innerHTML = items.map(s=>`
            <tr>
                <td class="px-3 py-2 whitespace-nowrap">${s.student_id}</td>
                <td class="px-3 py-2">${s.full_name}</td>
                <td class="px-3 py-2">G${s.grade_level}${s.section? ' / '+s.section: ''}</td>
                <td class="px-3 py-2">${s.strand}</td>
                <td class="px-3 py-2">
                    <div class="flex items-center gap-2">
                        <button class="btn-sm" data-act="view" data-id="${s.id}">View</button>
                        <button class="btn-sm warn" data-act="edit" data-id="${s.id}">Edit</button>
                        <button class="btn-sm danger" data-act="del" data-id="${s.id}">Delete</button>
                    </div>
                </td>
            </tr>`).join('') || '<tr><td class="px-3 py-2" colspan="5">No students found.</td></tr>';

        tbody.querySelectorAll('button').forEach(btn=>{
            btn.addEventListener('click', ()=> handleStudentAction(btn.dataset));
        });
    } catch (error) {
        console.error('Error loading students:', error);
    }
}

// Strand filter event listener - FIXED
$('#filterStrand').addEventListener('change', function() {
    // Clear search when strand filter changes
    $('#searchStudent').value = '';
    renderStudentTable();
});

async function handleStudentAction({act, id}){
    try {
        if(act==='view'){
            const s = await apiCall(`students.php?id=${id}`);
            
            $('#studentDetail').innerHTML = `
                <div class="grid gap-2">
                    <div><span class="muted">Name</span><div class="font-medium">${s.full_name}</div></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><span class="muted">Student ID</span><div>${s.student_id}</div></div>
                        <div><span class="muted">Strand</span><div>${s.strand}</div></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><span class="muted">Grade</span><div>${s.grade_level}</div></div>
                        <div><span class="muted">Section</span><div>${s.section||'-'}</div></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><span class="muted">Username</span><div>${s.username || 'Not set'}</div></div>
                        <div><span class="muted">Password</span><div>••••••••</div></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><span class="muted">Student Type</span><div>${s.is_irregular ? 'Irregular' : 'Regular'}</div></div>
                        <div><span class="muted">Updated</span><div>${fmt(s.updated_at)}</div></div>
                    </div>
                </div>`;
            
            // Set current student ID and show add subject button
            currentStudentId = id;
            $('#btnAddSubject').classList.remove('hidden');
            
            // Load student subjects
            loadStudentSubjects(id);
        }
        if(act==='edit'){
            const s = await apiCall(`students.php?id=${id}`);
            openEditModal(s);
        }
        if(act==='del'){
            if(!confirm('Delete this student?')) return;
            const student = await apiCall(`students.php?id=${id}`);
            await apiCall(`students.php?id=${id}`, 'DELETE');
            
            addNotification(
                'warning',
                'Student Deleted',
                `Student ${student.full_name} (${student.student_id}) has been deleted`
            );
            
            $('#studentDetail').textContent = 'Select a student to view details…';
            $('#studentSubjectsList').innerHTML = '<div class="text-center text-gray-500 py-4">Select a student to view subjects</div>';
            $('#btnAddSubject').classList.add('hidden');
            currentStudentId = null;
            renderStudentTable();
            renderDashboard();
        }
    } catch (error) {
        addNotification(
            'error',
            'Operation Failed',
            `Failed to perform student operation: ${error.message}`
        );
    }
}

// ====== TEACHERS CRUD - FIXED VERSION ======
$('#teacherForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Validate required fields
        if (!data.firstName || !data.lastName || !data.username || !data.password) {
            addNotification('error', 'Missing Fields', 'Please fill all required fields');
            return;
        }
        
        // Create the data structure expected by the API
        const apiData = {
            name: `${data.firstName} ${data.middleName ? data.middleName + ' ' : ''}${data.lastName}`.trim(),
            email: data.email || '',
            strand: data.strand,
            username: data.username,
            password: data.password
        };
        
        console.log('Sending teacher data:', apiData);
        
        const result = await apiCall('teachers.php', 'POST', apiData);
        
        // Clear form
        e.target.reset();
        
        // Add success notification
        addNotification(
            'success',
            'Teacher Added', 
            `Teacher ${apiData.name} has been added to ${data.strand}`,
            { teacherName: apiData.name, strand: data.strand }
        );
        
        // Refresh teacher lists
        renderTeacherTable();
        loadTeachersForAssignments();
        
    } catch (error) {
        console.error('Teacher creation error:', error);
        addNotification(
            'error',
            'Teacher Creation Failed',
            `Failed to add teacher: ${error.message}`
        );
    }
});

// Edit teacher form submission - FIXED
$('#editTeacherForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Create the data structure for update
        const apiData = {
            name: `${data.firstName} ${data.middleName ? data.middleName + ' ' : ''}${data.lastName}`.trim(),
            email: data.email || '',
            strand: data.strand,
            username: data.username
        };
        
        // Add password only if provided
        if (data.password && data.password.trim() !== '') {
            apiData.password = data.password;
        }
        
        console.log('Updating teacher data:', apiData);
        
        await apiCall(`teachers.php?id=${data.id}`, 'PUT', apiData);
        
        addNotification(
            'success',
            'Teacher Updated',
            `Teacher ${apiData.name} has been updated successfully`
        );
        
        closeEditTeacherModal();
        renderTeacherTable();
        loadTeachersForAssignments();
    } catch (error) {
        console.error('Teacher update error:', error);
        addNotification(
            'error',
            'Update Failed',
            `Failed to update teacher: ${error.message}`
        );
    }
});

async function renderTeachers(){
    $('#filterTeacherStrand').value = 'ALL';
    $('#searchTeacher').value = '';
    await renderTeacherTable();
}

// ====== FIXED TEACHER TABLE RENDERING ======
async function renderTeacherTable(teachersToRender = null){
    try {
        const filter = $('#filterTeacherStrand').value;
        const searchTerm = $('#searchTeacher').value.toLowerCase();
        let teachers;
        
        if (teachersToRender) {
            // If we have filtered teachers from search, use them
            teachers = teachersToRender;
        } else {
            // Otherwise, fetch all teachers from API
            teachers = await apiCall('teachers.php');
            allTeachers = teachers; // Store for search functionality
        }
        
        // Apply strand filter if needed (only when not searching)
        if (filter !== 'ALL' && !searchTerm) {
            teachers = teachers.filter(teacher => teacher.strand === filter);
        }
        
        const tbody = $('#teacherTable');
        tbody.innerHTML = teachers.map(teacher => {
            return `
            <tr>
                <td class="px-3 py-2">${teacher.name}</td>
                <td class="px-3 py-2">${teacher.strand}</td>
                <td class="px-3 py-2">${teacher.username}</td>
                <td class="px-3 py-2">
                    <div class="flex items-center gap-2">
                        <button class="btn-sm" data-act="view" data-id="${teacher.id}">View</button>
                        <button class="btn-sm warn" data-act="edit" data-id="${teacher.id}">Edit</button>
                        <button class="btn-sm danger" data-act="del" data-id="${teacher.id}">Delete</button>
                    </div>
                </td>
            </tr>`;
        }).join('') || '<tr><td class="px-3 py-2" colspan="4">No teachers found.</td></tr>';

        tbody.querySelectorAll('button').forEach(btn=>{
            btn.addEventListener('click', ()=> handleTeacherAction(btn.dataset));
        });
    } catch (error) {
        console.error('Error loading teachers:', error);
    }
}

// Teacher strand filter event listener - FIXED
$('#filterTeacherStrand').addEventListener('change', function() {
    // Clear search when strand filter changes
    $('#searchTeacher').value = '';
    renderTeacherTable();
});

async function handleTeacherAction({act, id}){
    try {
        if(act==='view'){
            const teacher = await apiCall(`teachers.php?id=${id}`);
            
            $('#teacherDetail').innerHTML = `
                <div class="grid gap-2">
                    <div>
                        <span class="muted">Name</span>
                        <div class="font-medium">${teacher.name}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <span class="muted">Email</span>
                            <div>${teacher.email || '—'}</div>
                        </div>
                        <div>
                            <span class="muted">Strand</span>
                            <div>${teacher.strand}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <span class="muted">Username</span>
                            <div class="font-mono text-sm">${teacher.username}</div>
                        </div>
                        <div>
                            <span class="muted">Password</span>
                            <div class="flex items-center gap-2">
                                <div class="font-mono text-sm" id="passwordDisplay">••••••••</div>
                                <button
                                    class="text-xs text-indigo-600 hover:text-indigo-800"
                                    onclick="togglePasswordVisibility(${id})"
                                    id="togglePasswordBtn"
                                >
                                    <i class="fas fa-eye"></i> Show
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <span class="muted">Created</span>
                            <div>${fmt(teacher.created_at)}</div>
                        </div>
                        <div>
                            <span class="muted">Last Login</span>
                            <div>${teacher.last_login ? fmt(teacher.last_login) : 'Never'}</div>
                        </div>
                    </div>
                </div>
            `;
            
            // Reset password visibility state for this teacher
            passwordVisibility[id] = false;
            
            // Set current teacher ID and show assign subjects button
            currentTeacherId = id;
            $('#btnAssignSubjects').classList.remove('hidden');
            
            // Load teacher subjects into the Teacher Subjects panel
            loadTeacherSubjects(id);
        }

        if(act==='edit'){
            const teacher = await apiCall(`teachers.php?id=${id}`);
            openEditTeacherModal(teacher);
        }

        if(act==='del'){
            if(!confirm('Delete this teacher?')) return;
            const teacher = await apiCall(`teachers.php?id=${id}`);
            await apiCall(`teachers.php?id=${id}`, 'DELETE');
            
            addNotification(
                'warning',
                'Teacher Deleted',
                `Teacher ${teacher.name} has been removed from the system`
            );
            
            $('#teacherDetail').textContent = 'Select a teacher to view details…';
            $('#teacherSubjectsList').innerHTML = '<div class="text-center text-gray-500 py-4 col-span-full">Select a teacher to view assigned subjects</div>';
            $('#btnAssignSubjects').classList.add('hidden');
            currentTeacherId = null;
            renderTeachers();
            loadTeachersForAssignments();
        }
    } catch (error) {
        addNotification(
            'error',
            'Operation Failed',
            `Failed to perform teacher operation: ${error.message}`
        );
    }
}

/* ✅ NEW: Load subjects assigned to the selected teacher */
async function loadTeacherSubjects(teacherId) {
    const container = $('#teacherSubjectsList');
    if (!container) return;

    // Loading state
    container.innerHTML = `
        <div class="text-center text-gray-500 py-4 col-span-full">
            <i class="fas fa-spinner fa-spin mr-2"></i>
            Loading teacher subjects...
        </div>
    `;

    try {
        const subjects = await apiCall(`teacher-subjects.php?teacherId=${teacherId}`);

        if (!Array.isArray(subjects) || subjects.length === 0) {
            container.innerHTML = `
                <div class="text-center text-gray-500 py-4 col-span-full">
                    This teacher has no assigned subjects yet.
                </div>
            `;
            return;
        }

        const cardsHtml = subjects.map(subject => {
            const grade  = subject.grade_level ? `Grade ${subject.grade_level}` : '';
            const strand = subject.strand || '';
            const quarter = subject.quarter ? `Quarter ${subject.quarter}` : '';

            const meta = [grade, strand, quarter].filter(Boolean).join(' • ');

            return `
                <div class="teacher-subject-card">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold">
                                ${(subject.subject_code || '')} - ${(subject.subject_name || '')}
                            </div>
                            ${meta ? `
                                <div class="mt-1 text-xs text-gray-500">
                                    ${meta}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = cardsHtml;
    } catch (error) {
        console.error('Error loading teacher subjects:', error);
        container.innerHTML = `
            <div class="text-center text-red-500 py-4 col-span-full">
                <div class="font-semibold">Error loading teacher subjects</div>
                <div class="text-xs">${error.message}</div>
            </div>
        `;
    }
}

// Assign subjects button handler
$('#btnAssignSubjects').addEventListener('click', () => {
    if (currentTeacherId) {
        openAssignTeacherSubjectsModal(currentTeacherId);
    }
});

// Load subjects based on selected strand, grade level, and quarter
async function loadSubjectsForMaterialForm() {
    const strand = $('#materialForm select[name="strand"]').value;
    const gradeLevel = $('#materialForm select[name="gradeLevel"]').value;
    const quarter = $('#materialForm select[name="quarter"]').value;
    const subjectSelect = $('#materialForm select[name="subject"]');
    
    // Check if all required fields are selected
    if (strand && gradeLevel && quarter) {
        try {
            // Enable subject select
            subjectSelect.disabled = false;
            
            // Load subjects from database
            const subjects = await apiCall(`subjects.php?strand=${strand}&gradeLevel=${gradeLevel}`);

            if (subjects.length > 0) {
                subjectSelect.innerHTML = '<option value="">Select Subject</option>' +
                    subjects.map(subject => {
                        // use the numeric ID
                        return `<option value="${subject.id}">${subject.subject_code} - ${subject.subject_name}</option>`;
                    }).join('');
            } else {
                subjectSelect.innerHTML = '<option value="">No subjects found for this strand and grade level</option>';
            }

        } catch (error) {
            console.error('Error loading subjects:', error);
            subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
        }
    } else {
        // Disable subject select and show message
        subjectSelect.disabled = true;
        subjectSelect.innerHTML = '<option value="">Please select Strand, Grade Level and Quarter first</option>';
    }
}

// Load subjects for assignments based on selected teacher
async function loadSubjectsForAssignmentForm() {
    const teacherId = $('#assignmentTeacherSelect').value;
    const subjectSelect = $('#assignmentSubjectSelect');
    
    if (teacherId) {
        try {
            // Enable subject select
            subjectSelect.disabled = false;
            
            // Load subjects assigned to this teacher
            const subjects = await apiCall(`teacher-subjects.php?teacherId=${teacherId}`);

            if (subjects.length > 0) {
                subjectSelect.innerHTML = '<option value="">Select Subject</option>' +
                    subjects.map(subject => {
                        // again, use the numeric ID coming from API
                        return `<option value="${subject.id}">${subject.subject_code} - ${subject.subject_name}</option>`;
                    }).join('');
            } else {
                subjectSelect.innerHTML = '<option value="">No subjects assigned to this teacher</option>';
            }

        } catch (error) {
            console.error('Error loading teacher subjects:', error);
            subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
        }
    } else {
        // Disable subject select and show message
        subjectSelect.disabled = true;
        subjectSelect.innerHTML = '<option value="">Please select a teacher first</option>';
    }
}

// Add event listeners for strand, grade level, and quarter changes
document.addEventListener('DOMContentLoaded', function() {
    const strandSelect = $('#materialForm select[name="strand"]');
    const gradeLevelSelect = $('#materialForm select[name="gradeLevel"]');
    const quarterSelect = $('#materialForm select[name="quarter"]');
    const teacherSelect = $('#assignmentTeacherSelect');
    
    if (strandSelect) {
        strandSelect.addEventListener('change', loadSubjectsForMaterialForm);
    }
    if (gradeLevelSelect) {
        gradeLevelSelect.addEventListener('change', loadSubjectsForMaterialForm);
    }
    if (quarterSelect) {
        quarterSelect.addEventListener('change', loadSubjectsForMaterialForm);
    }
    if (teacherSelect) {
        loadTeachersForAssignmentForm(); 
        teacherSelect.addEventListener('change', loadSubjectsForAssignmentForm);
    }
});

async function loadMaterials() {
    try {
        const quarterFilter = $('#materialQuarterFilter').value;
        const strandFilter = $('#materialStrandFilter').value;
        const gradeFilter = $('#materialGradeFilter').value;
        
        let endpoint = 'materials.php?';
        const params = [];
        if (quarterFilter !== 'ALL') params.push(`quarter=${quarterFilter}`);
        if (strandFilter !== 'ALL') params.push(`strand=${strandFilter}`);
        if (gradeFilter !== 'ALL') params.push(`gradeLevel=${gradeFilter}`);
        
        endpoint += params.join('&');
        
        const materials = await apiCall(endpoint);
        renderMaterialsList(materials);
    } catch (error) {
        console.error('Error loading materials:', error);
    }
}

function renderMaterialsList(materials) {
    const container = $('#materialsList');
    
    if (materials.length === 0) {
        container.innerHTML = '<div class="col-span-3 text-center text-gray-500 py-8">No learning materials found</div>';
        return;
    }
    
    container.innerHTML = materials.map(material => `
        <div class="material-card bg-white rounded-lg border border-gray-200 p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-start justify-between mb-2">
                <div class="flex items-center gap-2">
                    <span class="${getQuarterBadgeClass(material.quarter)}">Q${material.quarter}</span>
                    <span class="text-xs px-2 py-1 bg-indigo-100 text-indigo-800 rounded dark:bg-indigo-900/30 dark:text-indigo-300">
                        ${material.type}
                    </span>
                </div>
                <button class="text-gray-400 hover:text-gray-600" onclick="deleteMaterial(${material.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <h4 class="font-semibold mb-2">${material.title}</h4>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">${material.description || 'No description'}</p>
            <div class="flex items-center justify-between text-xs text-gray-500">
                <div>
                    <div>${material.strand} • Grade ${material.grade_level}</div>
                    <div>${material.subject}</div>
                </div>
                <span>${new Date(material.created_at).toLocaleDateString()}</span>
            </div>
            ${material.file_url ? `
                <div class="mt-3">
                    <a href="${material.file_url}" target="_blank" 
                       class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                        <i class="fas fa-download"></i> Download
                    </a>
                </div>
            ` : ''}
        </div>
    `).join('');
}

async function deleteMaterial(id) {
    if (!confirm('Are you sure you want to delete this material?')) return;
    
    try {
        const material = await apiCall(`materials.php?id=${id}`);
        await apiCall(`materials.php?id=${id}`, 'DELETE');
        
        addNotification(
            'warning',
            'Material Deleted',
            `"${material.title}" has been deleted from the library`
        );
        
        loadMaterials();
        renderDashboard(); // Refresh quarter stats
    } catch (error) {
        addNotification(
            'error',
            'Deletion Failed',
            `Failed to delete material: ${error.message}`
        );
    }
}

// Filter events for materials
$('#materialQuarterFilter').addEventListener('change', loadMaterials);
$('#materialStrandFilter').addEventListener('change', loadMaterials);
$('#materialGradeFilter').addEventListener('change', loadMaterials);

// ====== ASSIGNMENTS & TASKS ======
$('#assignmentForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const form = e.target;
    const fileInput = document.getElementById('assignmentFileInput');
    const fileNameDisplay = document.getElementById('assignmentFileNameDisplay');

    try {
        const formData = new FormData(form);

        // Extra fields for DB
        formData.append('distributionType', 'teacher');
        formData.append('status', 'active');

        const res = await fetch(API_BASE + 'assignments.php', {
            method: 'POST',
            body: formData, // multipart/form-data with file (if any)
        });

        const text = await res.text();
        console.log('Assignment create response:', text);

        let json;
        try {
            json = JSON.parse(text);
        } catch (err) {
            throw new Error('Invalid JSON from server: ' + text);
        }

        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Creation failed');
        }

        // Reset form + file UI
        form.reset();
        if (fileInput) fileInput.value = '';
        if (fileNameDisplay) {
            fileNameDisplay.textContent = 'No file selected';
            fileNameDisplay.classList.remove('has-file');
        }

        addNotification(
            'success',
            'Assignment Created',
            `"${json.data?.title || 'New assignment'}" has been created.`
        );

        // Refresh list and dashboard stats
        await loadAssignments();
        renderDashboard();
    } catch (error) {
        console.error('Error creating assignment:', error);
        addNotification(
            'error',
            'Assignment Creation Failed',
            `Failed to create assignment: ${error.message}`
        );
    }
});

async function loadAssignments() {
    try {
        const quarterFilter = $('#assignmentQuarterFilter').value;
        const strandFilter = $('#assignmentStrandFilter').value;
        const statusFilter = $('#assignmentStatusFilter').value;
        
        let endpoint = 'assignments.php?';
        const params = [];
        if (quarterFilter !== 'ALL') params.push(`quarter=${quarterFilter}`);
        if (strandFilter !== 'ALL') params.push(`strand=${strandFilter}`);
        if (statusFilter !== 'ALL') params.push(`status=${statusFilter}`);
        
        endpoint += params.join('&');
        
        const assignments = await apiCall(endpoint);
        renderAssignmentsList(assignments);
    } catch (error) {
        console.error('Error loading assignments:', error);
    }
}

function renderAssignmentsList(assignments) {
    const container = $('#assignmentsList');
    
    if (assignments.length === 0) {
        container.innerHTML = '<div class="text-center text-gray-500 py-8">No assignments found</div>';
        return;
    }
    
    container.innerHTML = assignments.map(assignment => `
        <div class="assignment-card bg-white rounded-lg border border-gray-200 p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="${getQuarterBadgeClass(assignment.quarter)}">Q${assignment.quarter}</span>
                    <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded dark:bg-green-900/30 dark:text-green-300">
                        ${assignment.type}
                    </span>
                    <span class="text-xs text-gray-500">${assignment.strand}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs px-2 py-1 ${assignment.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'} rounded">
                        ${assignment.status}
                    </span>
                    <button class="text-gray-400 hover:text-gray-600" onclick="deleteAssignment(${assignment.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <h4 class="font-semibold mb-2">${assignment.title}</h4>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">${assignment.instructions}</p>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-gray-500">Due Date</div>
                    <div class="font-medium">${new Date(assignment.due_date).toLocaleDateString()}</div>
                </div>
                <div>
                    <div class="text-gray-500">Max Score</div>
                    <div class="font-medium">${assignment.max_score}</div>
                </div>
                <div>
                    <div class="text-gray-500">Assigned To</div>
                    <div class="font-medium">${assignment.teacher_name}</div>
                </div>
                <div>
                    <div class="text-gray-500">Weight</div>
                    <div class="font-medium">${assignment.weight || 100}%</div>
                </div>
            </div>
            ${assignment.file_url ? `
                <div class="mt-3">
                    <a href="${assignment.file_url}" target="_blank" 
                       class="inline-flex items-center gap-1 text-sm text-green-600 hover:text-green-800 dark:text-green-400">
                        <i class="fas fa-download"></i> Download Files
                    </a>
                </div>
            ` : ''}
        </div>
    `).join('');
}

async function deleteAssignment(id) {
    if (!confirm('Are you sure you want to delete this assignment?')) return;
    
    try {
        const assignment = await apiCall(`assignments.php?id=${id}`);
        await apiCall(`assignments.php?id=${id}`, 'DELETE');
        
        addNotification(
            'warning',
            'Assignment Deleted',
            `"${assignment.title}" has been deleted`
        );
        
        loadAssignments();
        renderDashboard(); // Refresh dashboard
    } catch (error) {
        addNotification(
            'error',
            'Deletion Failed',
            `Failed to delete assignment: ${error.message}`
        );
    }
}

// Filter events for assignments
$('#assignmentQuarterFilter').addEventListener('change', loadAssignments);
$('#assignmentStrandFilter').addEventListener('change', loadAssignments);
$('#assignmentStatusFilter').addEventListener('change', loadAssignments);

// ====== QUARTERLY STATISTICS ======
async function loadQuarterlyStats() {
    try {
        const stats = await apiCall('stats/quarters.php');
        
        // Update distribution section
        $('#q1Materials').textContent = stats.q1?.materials || 0;
        $('#q1Assignments').textContent = stats.q1?.assignments || 0;
        $('#q1Performance').textContent = stats.q1?.performance_tasks || 0;
        
        $('#q2Materials').textContent = stats.q2?.materials || 0;
        $('#q2Assignments').textContent = stats.q2?.assignments || 0;
        $('#q2Performance').textContent = stats.q2?.performance_tasks || 0;
        
        $('#q3Materials').textContent = stats.q3?.materials || 0;
        $('#q3Assignments').textContent = stats.q3?.assignments || 0;
        $('#q3Performance').textContent = stats.q3?.performance_tasks || 0;
        
        $('#q4Materials').textContent = stats.q4?.materials || 0;
        $('#q4Assignments').textContent = stats.q4?.assignments || 0;
        $('#q4Performance').textContent = stats.q4?.performance_tasks || 0;
    } catch (error) {
        console.error('Error loading quarterly stats:', error);
    }
}

// ====== TEACHER WORKLOAD ======
async function loadTeacherWorkload() {
    try {
        const workload = await apiCall('teachers/workload.php');
        const container = $('#teacherWorkload');
        
        container.innerHTML = workload.map(teacher => {
            return `
            <div class="p-3 border border-gray-200 rounded-lg dark:border-gray-700">
                <div class="flex justify-between items-start mb-2">
                    <div class="font-medium">${teacher.name}</div>
                    <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded dark:bg-blue-900/30 dark:text-blue-300">
                        ${teacher.assignment_count} assignments
                    </span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                    <div>Strand: ${teacher.strand}</div>
                    <div>Students: ${teacher.student_count}</div>
                </div>
                <div class="grid grid-cols-4 gap-1 text-xs">
                    ${QUARTERS.map(q => `
                        <div class="text-center p-1 rounded ${getQuarterBadgeClass(q)}">
                            <div>Q${q}</div>
                            <div class="font-bold">${teacher[`q${q}_assignments`] || 0}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `}).join('') || '<div class="text-gray-500">No workload data available</div>';
    } catch (error) {
        console.error('Error loading teacher workload:', error);
    }
}

// ====== QUICK DISTRIBUTION ======
$('#quickDistributeForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        await apiCall('distribution.php', 'POST', data);
        e.target.reset();
        
        addNotification(
            'success',
            'Content Distributed',
            `${data.contentType} has been distributed for Quarter ${data.quarter}`
        );
    } catch (error) {
        addNotification(
            'error',
            'Distribution Failed',
            `Failed to distribute content: ${error.message}`
        );
    }
});

// ====== EXPORT FUNCTION ======
$('#btnExport').addEventListener('click', async ()=>{
    try {
        const data = await apiCall('export.php');
        const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'allshs_elms_data.json';
        a.click();
        URL.revokeObjectURL(url);
        
        addNotification(
            'success',
            'Data Exported',
            'All system data has been exported successfully'
        );
    } catch (error) {
        addNotification(
            'error',
            'Export Failed',
            `Failed to export data: ${error.message}`
        );
    }
});

// ====== NOTIFICATION UI HANDLERS ======
$('#btnNotifications').addEventListener('click', (e) => {
    e.stopPropagation();
    const dropdown = $('#notificationDropdown');
    dropdown.classList.toggle('hidden');
    
    // Mark all as read when opening
    if (!dropdown.classList.contains('hidden')) {
        notifications.active.forEach(n => n.read = true);
        notifications.unreadCount = 0;
        updateNotificationUI();
        saveNotifications();
    }
});

$('#btnViewArchive').addEventListener('click', (e) => {
    e.stopPropagation();
    $('#notificationDropdown').classList.add('hidden');
    
    // Show notifications section
    $$('#sidebar .nav-btn').forEach(b => b.classList.remove('active'));
    Object.entries(sections).forEach(([k, sec]) => {
        sec.classList.toggle('hidden', k !== 'notifications');
    });
    
    // Add temporary nav item for notifications
    const tempNav = document.createElement('button');
    tempNav.className = 'nav-btn active';
    tempNav.innerHTML = '📋 Notification Archive';
    tempNav.onclick = () => {
        tempNav.remove();
        showSection('dashboard');
    };
    $('#sidebar nav').appendChild(tempNav);
});

$('#btnClearNotifications').addEventListener('click', (e) => {
    e.stopPropagation();
    clearNotifications();
    $('#notificationDropdown').classList.add('hidden');
});

$('#btnClearArchive').addEventListener('click', () => {
    if (confirm('Are you sure you want to clear all archived notifications?')) {
        clearArchive();
    }
});

// Close notification dropdown when clicking outside
document.addEventListener('click', () => {
    $('#notificationDropdown').classList.add('hidden');
});

// ====== LEARNING MATERIALS ======
document.addEventListener('DOMContentLoaded', () => {
    const materialForm = document.getElementById('materialForm');
    if (!materialForm) return; // safety

    materialForm.addEventListener('submit', async (e) => {
        e.preventDefault(); // STOP normal form submit / page reload

        const form = e.target;

        // Check file
        const fileInput = document.getElementById('materialFileInput');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            addNotification('error', 'No file selected', 'Please choose a file to upload.');
            return;
        }

        try {
            const formData = new FormData(form);
            formData.append('directToStudents', '1');
            formData.append('status', 'published');

            // Send to /api/materials.php
            const res = await fetch(API_BASE + 'materials.php', {
                method: 'POST',
                body: formData,
            });

            const text = await res.text();
            console.log('Upload response:', text);

            let json;
            try {
                json = JSON.parse(text);
            } catch (err) {
                throw new Error('Server returned invalid JSON. Raw: ' + text);
            }

            if (!res.ok || !json.success) {
                throw new Error(json.message || 'Upload failed');
            }

            // Reset form + file label
            form.reset();
            fileInput.value = '';

            const label = document.getElementById('materialFileNameDisplay');
            if (label) {
                label.textContent = 'No file selected';
                label.classList.remove('has-file');
            }

            addNotification(
                'success',
                'Material Uploaded',
                `"${json.data.title}" saved to learning_materials.`
            );

            // Refresh list
            loadMaterials();
        } catch (err) {
            console.error(err);
            addNotification('error', 'Upload Failed', err.message);
        }
    });
});

// ====== INITIALIZATION ======
document.addEventListener('DOMContentLoaded', async ()=>{
    // Load notifications
    loadNotifications();
    
    // Initial data load
    await renderDashboard();
    await loadTeachersForAssignments();
    await renderTeachers();
    
    // Add welcome notification if first visit
    if (notifications.active.length === 0 && notifications.archive.length === 0) {
        addNotification(
            'info',
            'Welcome to Admin Panel',
            'You can manage students, materials, assignments, and teachers from here.'
        );
    }
});

// Navigation helper functions
function renderMaterials() { /* Initial render for materials section */ }
function renderAssignments() { /* Initial render for assignments section */ }
function renderDistribution() { /* Initial render for distribution section */ }

// Helper function to show sections
function showSection(sectionName) {
    $$('#sidebar .nav-btn').forEach(b => b.classList.remove('active'));
    Object.entries(sections).forEach(([k, sec]) => {
        sec.classList.toggle('hidden', k !== sectionName);
    });
}

// Add this debug script to your admin-home.php
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DEBUG: Admin Home Loaded ===');
    
    // Find all forms on the page
    const allForms = document.querySelectorAll('form');
    console.log('Found forms:', allForms.length);
    allForms.forEach((form, index) => {
        console.log(`Form ${index}:`, form.id, form.action, form.method);
    });

    // Find the upload materials form
    const uploadForm = document.querySelector('form[action*="materials"]') || 
                      document.querySelector('form[action*="upload"]') ||
                      document.getElementById('uploadMaterialForm') ||
                      document.querySelector('form');
    
    if (uploadForm) {
        console.log('=== DEBUG: Found upload form ===');
        console.log('Form action:', uploadForm.action);
        console.log('Form method:', uploadForm.method);
        console.log('Form enctype:', uploadForm.enctype);
        
        // Update form to use our debug endpoint
        uploadForm.action = 'api/debug-materials.php';
        uploadForm.method = 'POST';
        uploadForm.enctype = 'multipart/form-data';
        
        console.log('=== DEBUG: Updated form attributes ===');
        console.log('New form action:', uploadForm.action);
        console.log('New form method:', uploadForm.method);
        
        // Add submit event listener
        uploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('=== DEBUG: Form submission started ===');
            
            const formData = new FormData(this);
            console.log('=== DEBUG: FormData contents ===');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}:`, value);
            }

            try {
                const response = await fetch(this.action, {
                    method: this.method,
                    body: formData
                });

                console.log('=== DEBUG: Response received ===');
                console.log('Status:', response.status);
                console.log('Status text:', response.statusText);
                
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                try {
                    const data = JSON.parse(responseText);
                    console.log('Parsed JSON:', data);
                    
                    if (data.success) {
                        alert('SUCCESS: ' + data.message);
                        this.reset();
                    } else {
                        alert('ERROR: ' + data.message);
                    }
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response that failed to parse:', responseText);
                    alert('Server returned invalid JSON. Check console for details.');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                alert('Network error: ' + error.message);
            }
        });
        
    } else {
        console.error('=== DEBUG: No upload form found ===');
        // Create a debug form if none exists
        createDebugForm();
    }
});

// Load subjects for assignments based on selected teacher
async function loadSubjectsForAssignmentForm() {
    const teacherId = $('#assignmentTeacherSelect').value;
    const subjectSelect = $('#assignmentSubjectSelect');
    
    if (teacherId) {
        try {
            // Enable subject select
            subjectSelect.disabled = false;
            
            // Load subjects assigned to this teacher
            const subjects = await apiCall(`teacher-subjects.php?teacherId=${teacherId}`);
            
            if (subjects.length > 0) {
                subjectSelect.innerHTML = '<option value="">Select Subject</option>' +
                    subjects.map(subject => {
                        // use numeric subjects.id
                        return `<option value="${subject.id}">${subject.subject_code} - ${subject.subject_name}</option>`;
                    }).join('');
            } else {
                subjectSelect.innerHTML = '<option value="">No subjects assigned to this teacher</option>';
            }

        } catch (error) {
            console.error('Error loading teacher subjects:', error);
            subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
        }
    } else {
        // Disable subject select and show message
        subjectSelect.disabled = true;
        subjectSelect.innerHTML = '<option value="">Please select a teacher first</option>';
    }
}

// Load teachers into the assignment teacher dropdown (from database)
async function loadTeachersForAssignmentForm() {
    const teacherSelect = $('#assignmentTeacherSelect');
    if (!teacherSelect) return;

    // Show temporary loading text
    teacherSelect.innerHTML = '<option value="">Loading teachers...</option>';

    try {
        // Fetch teachers from teachers.php (same endpoint you use elsewhere)
        const teachers = await apiCall('teachers.php');

        if (!Array.isArray(teachers) || teachers.length === 0) {
            teacherSelect.innerHTML = '<option value="">No teachers found</option>';
            const subjectSelect = $('#assignmentSubjectSelect');
            if (subjectSelect) {
                subjectSelect.disabled = true;
                subjectSelect.innerHTML = '<option value="">Please add teachers first</option>';
            }
            return;
        }

        // Build dropdown options
        teacherSelect.innerHTML = '<option value="">Assign to Teacher</option>' +
            teachers.map(teacher => {
                // teachers.php returns { id, name, strand }
                return `<option value="${teacher.id}">${teacher.name} (${teacher.strand})</option>`;
            }).join('');

    } catch (error) {
        console.error('Error loading teachers for assignments:', error);
        teacherSelect.innerHTML = '<option value="">Error loading teachers</option>';
    }
}

// ====== DYNAMIC SECTION LOADING ======
async function loadSectionsForStudent() {
    const sectionSelect = document.getElementById('studentSection');
    if (!sectionSelect) return;

    sectionSelect.disabled = false;
    sectionSelect.innerHTML = `
        <option value="">Select Section</option>
        <option value="A">A</option>
        <option value="B">B</option>
        <option value="C">C</option>
        <option value="D">D</option>
    `;
}

document.addEventListener('DOMContentLoaded', () => {
    // Open reply modal
    const openReplyModal = (ticketId, studentName, studentEmail) => {
        document.getElementById('ticketName').textContent = studentName;
        document.getElementById('ticketEmail').textContent = studentEmail;
        document.getElementById('replyModal').classList.remove('hidden');

        // Store ticket info for later use (to send reply)
        document.getElementById('sendReplyBtn').onclick = () => sendReply(ticketId, studentEmail);
    };

    // Close modal
    document.getElementById('closeModalBtn').addEventListener('click', () => {
        document.getElementById('replyModal').classList.add('hidden');
    });

    // Send reply
    async function sendReply(ticketId, studentEmail) {
        const message = document.getElementById('replyMessage').value;
        if (!message.trim()) {
            alert("Please write a reply before sending.");
            return;
        }

        // Prepare data to send to the backend or directly to email
        const data = {
            ticket_id: ticketId,
            student_email: studentEmail,
            reply_message: message
        };

        try {
            const response = await fetch('/api/send-ticket-reply.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                alert("Reply sent successfully!");
                // Close the modal
                document.getElementById('replyModal').classList.add('hidden');
                // Optionally, refresh the ticket list or update the UI here
            } else {
                alert("Failed to send reply. Try again later.");
            }
        } catch (error) {
            console.error('Error:', error);
            alert("Error sending reply. Please try again.");
        }
    }

    // Assuming you have a way to open this modal, for example:
    document.querySelectorAll('.reply-ticket-btn').forEach(button => {
        button.addEventListener('click', () => {
            const ticketId = button.dataset.ticketId;
            const studentName = button.dataset.studentName;
            const studentEmail = button.dataset.studentEmail;

            openReplyModal(ticketId, studentName, studentEmail);
        });
    });
});

  </script>
</body>
</html>