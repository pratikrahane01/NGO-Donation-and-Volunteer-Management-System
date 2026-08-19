import re
import glob

replacement = """<div class="modal-body">
                    
                    <div class="form-section">
                        <div class="form-section-title">Campaign Details</div>
                        <div class="form-group">
                            <label class="form-label">Campaign Name *</label>
                            <input class="form-control" type="text" name="name" value="<?php echo $campaign ? htmlspecialchars($campaign['name']) : ''; ?>" required>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Category *</label>
                                <select class="form-control" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($campaign && $campaign['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Target Amount (₹) *</label>
                                <input class="form-control" type="number" step="0.01" name="target_amount" value="<?php echo $campaign ? htmlspecialchars($campaign['target_amount']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">Schedule</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input class="form-control" type="date" name="start_date" value="<?php echo $campaign ? $campaign['start_date'] : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input class="form-control" type="date" name="end_date" value="<?php echo $campaign ? $campaign['end_date'] : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">Description</div>
                        <div class="form-group">
                            <label class="form-label">Short Description (for cards) *</label>
                            <textarea class="form-control" name="short_description" rows="2" maxlength="255" required><?php echo $campaign ? htmlspecialchars($campaign['short_description']) : ''; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Full Description (for details page)</label>
                            <textarea class="form-control" name="description" rows="5"><?php echo $campaign ? htmlspecialchars($campaign['description']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">Settings</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Status *</label>
                                <select class="form-control" name="status" required>
                                    <option value="draft" <?php echo ($campaign && $campaign['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                    <option value="active" <?php echo ($campaign && $campaign['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo ($campaign && $campaign['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo ($campaign && $campaign['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group" style="display: flex; align-items: center;">
                                <label class="custom-checkbox" style="margin-top: 24px;">
                                    <input type="checkbox" name="featured" value="1" <?php echo ($campaign && $campaign['featured']) ? 'checked' : ''; ?>>
                                    <span>Featured Campaign (appears on Homepage)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                </div>"""

for filepath in ['C:/xampp/htdocs/NGO-donation-management-system/ngo_campaigns.php', 'C:/xampp/htdocs/NGO-donation-management-system/admin_campaigns.php']:
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # We replace from <div class="modal-body"> to the closing </div> before <div class="modal-footer">
    content = re.sub(r'<div class="modal-body">.*?<div class="modal-footer">', replacement + '\n                <div class="modal-footer">', content, flags=re.DOTALL)
    
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
        print(f"Updated {filepath}")
