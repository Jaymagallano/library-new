<?php
// Template for action buttons with inline styles for icons
?>

<!-- Edit button -->
<a href="edit_user.php?id=<?php echo $user_id; ?>" class="btn-sm btn-primary" title="Edit">
    <i class="fas fa-edit" style="color: #ffffff;"></i>
</a>

<!-- Deactivate button -->
<form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to deactivate this user?');">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="action" value="deactivate">
    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
    <button type="submit" class="btn-sm btn-warning" title="Deactivate">
        <i class="fas fa-user-slash" style="color: #ffffff;"></i>
    </button>
</form>

<!-- Delete button -->
<form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
    <button type="submit" class="btn-sm btn-danger" title="Delete">
        <i class="fas fa-trash" style="color: #ffffff;"></i>
    </button>
</form>