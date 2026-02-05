<?php
/**
 * Editor Page Template - Full screen editor
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent admin bar
show_admin_bar(false);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($post_id ? get_the_title($post_id) : __('New Page', 'designstudio-flow')); ?> - DesignStudio Flow</title>
    <?php do_action( 'admin_head' ); ?>
    <style>
        /* Hide WordPress admin elements */
        html, body, #wpcontent, #wpbody, #wpbody-content {
            margin: 0 !important;
            padding: 0 !important;
            height: 100% !important;
            overflow: hidden !important;
        }
        #adminmenumain, #adminmenuback, #adminmenuwrap, #wpadminbar, #wpfooter {
            display: none !important;
        }
        #wpcontent {
            margin-left: 0 !important;
        }
        /* Editor app container */
        #dsf-editor-app {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1000; /* Lower z-index to allow WP dialogs */
        }
        
        /* Ensure WordPress Media Modal is above our editor */
        .media-modal-backdrop {
            z-index: 999990 !important;
        }
        
        .media-modal {
            z-index: 999999 !important;
        }
        
        .media-uploader-status {
            z-index: 999999 !important;
        }
    </style>
</head>
<body class="dsf-editor-body">
    <div id="dsf-editor-app">
        <!-- Vue.js app mounts here -->
        <div class="dsf-initial-loader">
            <div class="dsf-loader-content">
                <div class="dsf-logo-mark">
                    <img src="<?php echo esc_url( DSF_PLUGIN_URL . 'assets/images/dsflow-logo.png' ); ?>" alt="" loading="lazy" />
                </div>
                <div class="dsf-brand-text">
                    <span class="dsf-brand-main">DesignStudio</span>
                    <span class="dsf-brand-accent">Flow</span>
                </div>
                
                <div class="dsf-progress-container">
                    <div class="dsf-progress-bar"></div>
                </div>
                
                <p class="dsf-loader-msg">Initializing workspace...</p>
            </div>
        </div>
    </div>
    
    <style>
        .dsf-initial-loader {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            width: 100vw;
            background-color: #FFFFFF;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        .dsf-loader-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            opacity: 0;
            animation: dsfFadeIn 0.6s ease-out forwards;
        }

        .dsf-logo-mark {
            margin-bottom: 24px;
            width: 40px;
            height: 40px;
        }

        .dsf-logo-mark img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
            animation: dsfLogoPulse 2s ease-in-out infinite;
        }

        .dsf-brand-text {
            font-size: 24px;
            margin-bottom: 32px;
            color: #111827;
            letter-spacing: -0.5px;
        }

        .dsf-brand-main {
            font-weight: 600;
        }

        .dsf-brand-accent {
            font-weight: 400;
            color: #6B7280;
            margin-left: 6px;
        }

        .dsf-progress-container {
            width: 240px;
            height: 4px;
            background-color: #F3F4F6;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .dsf-progress-bar {
            height: 100%;
            width: 40%;
            background-color: #0E58E6;
            border-radius: 4px;
            animation: dsfLoading 2s ease-in-out infinite;
        }

        .dsf-loader-msg {
            color: #9CA3AF;
            font-size: 13px;
            font-weight: 500;
            margin: 0;
            animation: dsfPulseText 2s ease-in-out infinite;
        }

        @keyframes dsfFadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes dsfLoading {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); width: 60%; }
            100% { transform: translateX(200%); }
        }
        
        @keyframes dsfLogoPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(0.96); opacity: 0.75; }
        }

        @keyframes dsfPulseText {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }
    </style>
    
    <?php do_action( 'admin_footer' ); ?>
</body>
</html>
