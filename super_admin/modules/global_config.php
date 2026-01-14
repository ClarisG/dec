<?php
// System Configuration Module
$config = getSystemConfig();
?>

<div class="module-global-config">
    <h3>Global System Configuration</h3>
    
    <div class="config-section">
        <h4>AI Classification Settings</h4>
        <form id="ai-config-form">
            <div class="form-group">
                <label>Confidence Threshold:</label>
                <input type="number" step="0.01" min="0" max="1" 
                       value="<?php echo $config['classification_threshold']; ?>"
                       name="classification_threshold">
            </div>
            <!-- Add more config options -->
        </form>
    </div>
</div>