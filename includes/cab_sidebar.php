<?php 
// Ensure database connection is available
if (!isset($db)) {
    global $db;
}

$contact_phone = function_exists('getSetting') ? (getSetting('contact_phone') ?: getSetting('site_phone') ?: '+1 234 567 8900') : '+1 234 567 8900';
$contact_email = function_exists('getSetting') ? (getSetting('contact_email') ?: getSetting('site_email') ?: 'info@travhub.com') : 'info@travhub.com';
$site_address = function_exists('getSetting') ? (getSetting('site_address') ?: '123 Travel Street, City, Country') : '123 Travel Street, City, Country';
$opening_hours = function_exists('getSetting') ? (getSetting('opening_hours') ?: '9:00 AM - 6:00 PM') : '9:00 AM - 6:00 PM';
?>
<div class="cab-routes-sidebar">
    <!-- Sidebar Header - Purple Theme to match Featured Tours -->
    <div class="section-header mb-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 12px 14px; border-radius: 8px; box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);">
        <h4 style="font-size: 1.05rem; font-weight: 700; color: white; margin-bottom: 3px; line-height: 1.2;">
            <i class="fas fa-route"></i> 🚗 Transport Facilities
        </h4>
        <p style="color: rgba(255, 255, 255, 0.9); font-size: 0.72rem; margin: 0; line-height: 1.2;">Popular routes available</p>
    </div>
    
    <?php 
    $cab_routes = $db->fetchAll("
        SELECT * 
        FROM cab_routes 
        WHERE status = 'active' 
        ORDER BY display_order ASC 
        LIMIT 8
    ");

    foreach ($cab_routes as $route): 
        $cheapest = $db->fetch("
            SELECT MIN(crp.one_way_price) as min_price
            FROM cab_route_pricing crp
            WHERE crp.route_id = ? AND crp.status = 'active' AND crp.one_way_price > 0
        ", [$route['id']]);

        $starting_price = $cheapest['min_price'] ?? 0;
    ?>
    <div class="card mb-2" style="border: none; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; background: white; transition: transform 0.3s ease;">
        <!-- Route Header -->
        <div style="background: #f8f9fa; padding: 9px 12px; border-bottom: 1px solid #eee;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="color: #495057; font-weight: 700; font-size: 0.8rem; line-height: 1.2;">
                    <?php echo htmlspecialchars($route['from_location']); ?> <i class="fas fa-long-arrow-alt-right" style="color: #667eea;"></i> <?php echo htmlspecialchars($route['to_location']); ?>
                </span>
            </div>
        </div>
        
        <!-- Route Body -->
        <div class="card-body text-center" style="padding: 10px 12px;">
            <?php if ($starting_price > 0): ?>
                <div style="font-size: 0.72rem; color: #6c757d; margin-bottom: 1px; line-height: 1.1;">Starts from</div>
                <div style="font-size: 1.15rem; font-weight: 800; color: #764ba2; margin-bottom: 7px; line-height: 1.15;">
                    <?php echo formatPriceINR($starting_price); ?>
                </div>
            <?php else: ?>
                <div style="font-size: 0.82rem; color: #6c757d; margin-top: 2px; margin-bottom: 8px; line-height: 1.2;">Price on request</div>
            <?php endif; ?>
            
            <a href="<?php echo BASE_URL; ?>cab-route-details.php?route_id=<?php echo $route['id']; ?>" class="btn w-100 cab-route-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; color: white; border: none; border-radius: 6px; padding: 8px 12px; font-weight: 600; font-size: 0.82rem; box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3); line-height: 1.2;">
                <i class="fas fa-info-circle"></i> View Details
            </a>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="card mt-3" style="border: none; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; background: white;">
        <div style="background: #f8f9fa; padding: 10px 12px; border-bottom: 1px solid #eee;">
            <div style="color: #495057; font-weight: 700; font-size: 0.9rem; line-height: 1.2;">
                <i class="fas fa-headset" style="color: #667eea;"></i> Contact Information
            </div>
        </div>
        <div style="padding: 12px;">
            <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 10px; color: #495057; font-size: 0.82rem; line-height: 1.4;">
                <i class="fas fa-phone-alt" style="color: #667eea; margin-top: 2px;"></i>
                <a href="tel:<?php echo htmlspecialchars(str_replace(' ', '', $contact_phone)); ?>" style="color: #495057; text-decoration: none;">
                    <?php echo htmlspecialchars($contact_phone); ?>
                </a>
            </div>
            <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 10px; color: #495057; font-size: 0.82rem; line-height: 1.4;">
                <i class="fas fa-envelope" style="color: #667eea; margin-top: 2px;"></i>
                <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" style="color: #495057; text-decoration: none; word-break: break-word;">
                    <?php echo htmlspecialchars($contact_email); ?>
                </a>
            </div>
            <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 10px; color: #495057; font-size: 0.82rem; line-height: 1.4;">
                <i class="fas fa-map-marker-alt" style="color: #667eea; margin-top: 2px;"></i>
                <span><?php echo htmlspecialchars($site_address); ?></span>
            </div>
            <div style="display: flex; align-items: flex-start; gap: 8px; color: #495057; font-size: 0.82rem; line-height: 1.4;">
                <i class="fas fa-clock" style="color: #667eea; margin-top: 2px;"></i>
                <span><?php echo htmlspecialchars($opening_hours); ?></span>
            </div>
        </div>
    </div>
</div>
