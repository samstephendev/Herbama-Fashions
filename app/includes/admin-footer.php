    </div><!-- /.admin-content -->
</div><!-- /.admin-main -->

<!-- Reusable Confirmation Modal -->
<div class="admin-modal-backdrop" id="confirmModal">
    <div class="admin-modal">
        <h3 id="confirmModalTitle">Are you sure?</h3>
        <p id="confirmModalMessage">This action cannot be undone. Please confirm to proceed.</p>
        <div class="admin-modal-actions">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeConfirmModal()">Cancel</button>
            <form id="confirmModalForm" method="POST" action="" style="margin:0;">
                <?= csrf_field() ?>
                <div id="confirmModalHiddenFields"></div>
                <button type="submit" id="confirmModalSubmitBtn" class="btn btn-danger btn-sm">Confirm</button>
            </form>
        </div>
    </div>
</div>

<script>
// Mobile Sidebar Toggle
function toggleSidebar() {
    const sb = document.getElementById('adminSidebar');
    if (sb) {
        sb.classList.toggle('open');
    }
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', (e) => {
    const sb = document.getElementById('adminSidebar');
    const btn = document.querySelector('.mobile-menu-btn');
    if (sb && sb.classList.contains('open') && !sb.contains(e.target) && (!btn || !btn.contains(e.target))) {
        sb.classList.remove('open');
    }
});

// Confirmation Modal Helper
function openConfirmModal(options) {
    const modal = document.getElementById('confirmModal');
    const title = document.getElementById('confirmModalTitle');
    const msg = document.getElementById('confirmModalMessage');
    const form = document.getElementById('confirmModalForm');
    const btn = document.getElementById('confirmModalSubmitBtn');
    const fieldsContainer = document.getElementById('confirmModalHiddenFields');

    title.innerText = options.title || 'Confirm Action';
    msg.innerText   = options.message || 'Are you sure you want to proceed?';
    form.action     = options.action || '';
    btn.innerText   = options.confirmText || 'Confirm';
    
    // Button styling
    btn.className = 'btn btn-sm ' + (options.btnClass || 'btn-danger');

    // Populate hidden inputs
    fieldsContainer.innerHTML = '';
    if (options.params) {
        for (const [key, value] of Object.entries(options.params)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            fieldsContainer.appendChild(input);
        }
    }

    modal.classList.add('active');
}

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeConfirmModal();
    }
});
</script>

</body>
</html>
