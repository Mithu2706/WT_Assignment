        </main>
        <footer class="app-footer">Student Management Portal · PHP admin module</footer>
    </div>
</div>

<!-- Confirmation dialog used by admin.js for delete actions (falls back to a confirm page without JS) -->
<dialog class="dialog" id="confirm-dialog" aria-labelledby="confirm-title">
    <form method="post" id="confirm-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="">
        <h2 id="confirm-title">Are you sure?</h2>
        <p id="confirm-message"></p>
        <div class="dialog-actions">
            <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-danger" id="confirm-submit">Delete</button>
        </div>
    </form>
</dialog>
</body>
</html>
