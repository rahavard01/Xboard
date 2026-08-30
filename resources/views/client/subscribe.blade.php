<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#081014">
    <title>USER INFO</title>

    @php
        $faNumber = function ($value) {
            return strtr((string) $value, [
                '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
                '.' => '٫',
            ]);
        };

        $faTraffic = function ($value) use ($faNumber) {
            $value = preg_replace('/\s*GB\s*/i', ' گیگابایت', (string) $value);
            return $faNumber(trim($value));
        };

        $usageWidth = max(0, min(100, (float) $usage_percent));

        $remainingDays = 'نامحدود';
        if (!empty($expired_date) && $expired_date !== 'بدون تاریخ انقضا') {
            try {
                $expiryDate = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', (string) $expired_date)
                    ->toCarbon()
                    ->startOfDay();

                $days = (int) now()->startOfDay()->diffInDays($expiryDate, false);

                if ($days < 0) {
                    $remainingDays = 'منقضی شده';
                } elseif ($days === 0) {
                    $remainingDays = 'امروز';
                } else {
                    $remainingDays = $faNumber($days) . ' روز';
                }
            } catch (\Throwable $e) {
                $remainingDays = '—';
            }
        }
    @endphp

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource-variable/vazirmatn@5.2.6/index.css" referrerpolicy="no-referrer">

    <style>
        :root {
            --app-font: "Vazirmatn Variable", "Vazirmatn", sans-serif;
        }

        html,
        body,
        body *,
        body *::before,
        body *::after,
        button,
        input,
        select,
        textarea,
        a,
        span,
        div,
        p,
        strong,
        small,
        label {
            font-family: var(--app-font) !important;
            font-optical-sizing: auto;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: var(--app-font) !important;
        }

        html,
        body,
        body *,
        body *::before,
        body *::after {
            font-family: var(--app-font) !important;
        }

        html {
            -webkit-text-size-adjust: 100%;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            overflow-x: hidden;
            color: #f5f7fa;
            background:
                radial-gradient(circle at 50% -20%, rgba(0, 184, 148, 0.07), transparent 34%),
                linear-gradient(180deg, #081014 0%, #0a0e12 100%);
            font-family: var(--app-font) !important;
            direction: rtl;
        }

        body.modal-open {
            overflow: hidden;
        }

        button,
        input {
            font: inherit;
        }

        button {
            -webkit-tap-highlight-color: transparent;
        }

        #network-canvas {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            background: transparent;
            z-index: 0;
            pointer-events: none;
        }

        .page {
            position: relative;
            z-index: 1;
            width: 100%;
            min-height: 100vh;
            padding: 34px 24px 58px;
        }

        .container {
            width: min(100%, 980px);
            margin: 0 auto;
        }

        .card {
            width: 100%;
            margin-bottom: 18px;
            overflow: hidden;
            border-radius: 14px;
            border: 1px solid rgba(0, 180, 255, 0.28);
            background:
                radial-gradient(120% 120% at 50% -10%, rgba(0, 160, 255, 0.14), rgba(0, 0, 0, 0) 60%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.04));
            box-shadow:
                0 8px 24px rgba(0, 0, 0, 0.28),
                inset 0 1px 0 rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
        }

        .card-header {
            position: relative;
            min-height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 17px 20px;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.15);
        }

        .card-header-icon {
            width: 19px;
            height: 19px;
            flex: 0 0 auto;
            color: rgba(201, 211, 222, 0.88);
        }

        .card-header-title {
            font-size: 14px;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.94);
        }

        .card-body {
            padding: 16px;
        }

        /* Subscription link */
        .subscription-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 186px;
            gap: 18px;
            align-items: center;
        }

        .subscription-main {
            min-width: 0;
        }

        .subscription-note {
            margin-bottom: 12px;
            color: rgba(255, 255, 255, 0.62);
            font-size: 11.5px;
            line-height: 1.9;
        }

        .link-box {
            direction: ltr;
            width: 100%;
            min-height: 46px;
            display: flex;
            align-items: center;
            padding: 11px 13px;
            margin-bottom: 10px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            border: 1px dashed rgba(255, 255, 255, 0.14);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.38);
            color: rgba(255, 255, 255, 0.69);
            font-family: var(--app-font);
            font-size: 11px;
            text-align: left;
        }

        .copy-button {
            width: 100%;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            border: 1px solid rgba(0, 180, 255, 0.38);
            border-radius: 10px;
            outline: none;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.95);
            font-size: 12.5px;
            font-weight: 750;
            background: linear-gradient(180deg, rgba(0, 128, 255, 0.43), rgba(0, 100, 210, 0.22));
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.11),
                0 5px 14px rgba(0, 0, 0, 0.2);
            transition: border-color 0.18s ease, background 0.18s ease, transform 0.18s ease;
        }

        .copy-button:hover {
            border-color: rgba(0, 180, 255, 0.58);
            background: linear-gradient(180deg, rgba(0, 128, 255, 0.54), rgba(0, 100, 210, 0.3));
        }

        .copy-button:active {
            transform: scale(0.995);
        }

        .copy-button.success {
            color: #b9f8cd;
            border-color: rgba(34, 197, 94, 0.42);
            background: rgba(34, 197, 94, 0.15);
        }

        .copy-icon {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
        }

        .qr-box {
            width: 170px;
            height: 170px;
            display: flex;
            align-items: center;
            justify-content: center;
            justify-self: center;
            padding: 9px;
            border: 4px solid #ff9800;
            border-radius: 17px;
            background: #ffffff;
            box-shadow:
                0 10px 28px rgba(0, 0, 0, 0.28),
                0 0 12px rgba(255, 152, 0, 0.18);
        }

        .qr-box img {
            display: block;
            width: 100%;
            height: 100%;
        }

        /* Subscription status */
        .account-strip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 13px;
            padding: 11px 13px;
            border: 1px dashed rgba(255, 255, 255, 0.13);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.30);
        }

        .username-box {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .username-icon {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border-radius: 9px;
            color: rgba(224, 233, 243, 0.86);
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.10);
        }

        .username-icon svg {
            width: 15px;
            height: 15px;
        }

        .username-text {
            min-width: 0;
        }

        .username-label {
            display: block;
            margin-bottom: 3px;
            color: rgba(255, 255, 255, 0.48);
            font-size: 10.5px;
        }

        .username-value {
            display: block;
            direction: ltr;
            max-width: 420px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            color: rgba(255, 255, 255, 0.94);
            font-size: 12.5px;
            font-weight: 750;
            text-align: right;
        }

        .status-badge {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-width: 74px;
            padding: 6px 11px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 750;
            line-height: 1;
        }

        .status-badge.active {
            color: #9df6bc;
            background: rgba(34, 197, 94, 0.10);
            border: 1px solid rgba(34, 197, 94, 0.31);
        }

        .status-badge.inactive {
            color: #ffaaaa;
            background: rgba(239, 68, 68, 0.10);
            border: 1px solid rgba(239, 68, 68, 0.31);
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex: 0 0 auto;
        }

        .active .status-dot {
            background: #22c55e;
            box-shadow: 0 0 8px rgba(34, 197, 94, 0.72);
        }

        .inactive .status-dot {
            background: #ef4444;
            box-shadow: 0 0 8px rgba(239, 68, 68, 0.72);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 9px;
        }

        .stat-box {
            min-height: 86px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 12px 8px;
            text-align: center;
            border: 1px dashed rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.37);
        }

        .stat-label {
            margin-bottom: 6px;
            color: rgba(255, 255, 255, 0.56);
            font-size: 11px;
        }

        .stat-value {
            direction: rtl;
            font-size: 14px;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.95);
            overflow-wrap: anywhere;
        }

        .progress-wrap {
            margin-top: 13px;
            padding: 13px 14px;
            border: 1px dashed rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.26);
        }

        .progress-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 9px;
            color: rgba(255, 255, 255, 0.60);
            font-size: 11px;
        }

        .progress-percent {
            direction: rtl;
            color: rgba(255, 255, 255, 0.91);
            font-weight: 800;
        }

        .progress-track {
            position: relative;
            width: 100%;
            height: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.11);
        }

        .progress-bar {
            position: absolute;
            top: 0;
            right: 0;
            height: 100%;
            width: {{ $usageWidth }}%;
            max-width: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, rgba(87, 166, 255, 0.96), rgba(0, 180, 255, 0.78));
            box-shadow: 0 0 10px rgba(0, 180, 255, 0.24);
        }

        /* Tutorials */
        .tutorial-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .tutorial-item {
            min-height: 108px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 10px;
            border: 1px dashed rgba(255, 255, 255, 0.13);
            border-radius: 11px;
            outline: none;
            cursor: pointer;
            color: #eaf2ff;
            background: rgba(0, 0, 0, 0.32);
            transition: transform 0.16s ease, background 0.16s ease, border-color 0.16s ease;
        }

        .tutorial-item:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.065);
            border-color: rgba(0, 180, 255, 0.30);
        }

        .platform-icon {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: rgba(237, 244, 252, 0.94);
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.10);
        }

        .platform-icon svg {
            width: 21px;
            height: 21px;
        }

        .platform-icon .apple-logo {
            width: 18px;
            height: 22px;
        }

        .platform-name {
            font-size: 12.5px;
            font-weight: 800;
        }

        .platform-app {
            direction: ltr;
            color: rgba(255, 255, 255, 0.48);
            font-size: 10px;
            font-weight: 600;
        }

        /* Tutorial modal - same visual family as panel Tutorials */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.70);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        .modal-backdrop.show {
            display: flex;
        }

        .modal-outer {
            width: min(100%, 680px);
            margin: auto;
        }

        .create-modal {
            width: 100%;
            padding: 20px 18px 17px;
            border-radius: 18px;
            color: #eaf2ff;
            background:
                radial-gradient(120% 120% at 50% -10%, rgba(0, 160, 255, 0.14), rgba(0, 0, 0, 0) 60%),
                linear-gradient(180deg, rgba(24, 31, 39, 0.97), rgba(12, 16, 21, 0.98));
            border: 1px solid rgba(0, 180, 255, 0.28);
            box-shadow:
                0 18px 48px rgba(0, 0, 0, 0.54),
                inset 0 1px 0 rgba(255, 255, 255, 0.11);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 13px;
            padding: 2px 8px 9px;
        }

        .modal-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 800;
        }

        .modal-platform-icon {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.11);
        }

        .modal-platform-icon svg {
            width: 18px;
            height: 18px;
        }

        .modal-platform-icon .apple-logo {
            width: 16px;
            height: 20px;
        }

        .final-result1 {
            width: 100%;
            padding: 18px 19px;
            border-radius: 16px;
            background:
                radial-gradient(120% 120% at 50% -10%, rgba(0, 160, 255, 0.14), rgba(0, 0, 0, 0) 60%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(0, 180, 255, 0.28);
            box-shadow:
                0 6px 18px rgba(0, 0, 0, 0.28),
                inset 0 1px 0 rgba(255, 255, 255, 0.12);
        }

        .section-block {
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.04);
            padding: 12px;
        }

        .section-block + .section-block {
            margin-top: 12px;
        }

        .section-head {
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 8px;
            margin-bottom: 10px;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.18);
            color: #eaf2ff;
            font-size: 12.5px;
            font-weight: 800;
        }

        .section-head svg {
            width: 15px;
            height: 15px;
            opacity: 0.9;
        }

        .app-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .app-summary-name {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
        }

        .app-summary-badge {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border-radius: 9px;
            color: rgba(239, 245, 252, 0.95);
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.11);
        }

        .app-summary-badge svg {
            width: 19px;
            height: 19px;
        }

        .app-summary-badge .apple-logo {
            width: 17px;
            height: 21px;
        }

        .app-name-text strong {
            display: block;
            direction: ltr;
            color: #eef5ff;
            font-size: 13px;
            text-align: right;
        }

        .app-name-text span {
            display: block;
            margin-top: 3px;
            color: rgba(255, 255, 255, 0.48);
            font-size: 9.5px;
        }

        .btn-mini {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 34px;
            padding: 7px 11px;
            cursor: pointer;
            color: #e9eef5;
            background: linear-gradient(180deg, rgba(37, 62, 90, 0.78), rgba(129, 167, 168, 0.36));
            border: 1px solid rgba(255, 255, 255, 0.30);
            border-radius: 10px;
            font-size: 10.5px;
            font-weight: 750;
        }

        .btn-mini:hover {
            filter: brightness(1.08);
        }

        .btn-mini svg {
            width: 13px;
            height: 13px;
        }

        .guide-steps {
            list-style: none;
            display: grid;
            gap: 8px;
        }

        .guide-step {
            display: grid;
            grid-template-columns: 26px minmax(0, 1fr);
            gap: 9px;
            align-items: start;
            padding: 9px 10px;
            border-radius: 9px;
            background: rgba(255, 255, 255, 0.03);
            color: #dbe4f2;
            font-size: 10.5px;
            font-weight: 500;
            line-height: 1.9;
            text-align: right;
        }

        .guide-step a {
            display: inline-block;
            max-width: 100%;
            direction: ltr;
            overflow-wrap: anywhere;
            word-break: break-all;
            color: #4da3ff;
            line-height: 1.8;
        }

        .speedbox-feature-intro {
            margin-bottom: 10px;
            color: #dbe4f2;
            font-size: 10.5px;
            font-weight: 500;
            line-height: 1.9;
            text-align: right;
        }

        .speedbox-features {
            list-style: none;
            display: grid;
            gap: 7px;
        }

        .speedbox-feature-item {
            position: relative;
            padding: 8px 24px 8px 10px;
            border-radius: 9px;
            background: rgba(255, 255, 255, 0.03);
            color: #dbe4f2;
            font-size: 10.5px;
            font-weight: 500;
            line-height: 1.9;
            text-align: right;
        }

        .speedbox-feature-item::before {
            content: "✓";
            position: absolute;
            top: 8px;
            right: 8px;
            color: #00b894;
            font-weight: 800;
        }

        .step-number {
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #dcefff;
            background: rgba(0, 123, 255, 0.19);
            border: 1px solid rgba(0, 180, 255, 0.20);
            font-size: 10px;
            font-weight: 800;
        }

        .modal-back-button {
            text-align: center;
        }

        .modal-back-button button {
            display: inline-block;
            padding: 8px 20px;
            margin-top: 15px;
            cursor: pointer;
            background: rgba(255, 0, 0, 0.4);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            font-size: 12px;
            font-family: var(--app-font) !important;
            font-weight: 300;
            transition: all 0.3s ease;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }

        .modal-back-button button:hover {
            background: rgba(255, 0, 0, 0.7);
            color: #fff;
            border-color: #fff;
        }

        .footer {
            margin-top: 23px;
            text-align: center;
            color: rgba(255, 255, 255, 0.29);
            font-size: 10px;
        }

        @media (max-width: 800px) {
            .page {
                padding: 25px 14px 42px;
            }

            .subscription-layout {
                grid-template-columns: minmax(0, 1fr) 160px;
            }

            .qr-box {
                width: 150px;
                height: 150px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .tutorial-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .page {
                padding: 18px 10px 32px;
            }

            .card {
                margin-bottom: 13px;
                border-radius: 12px;
            }

            .card-header {
                min-height: 58px;
                padding: 15px 12px;
            }

            .card-body {
                padding: 11px;
            }

            .subscription-layout {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .qr-box {
                grid-row: 1;
                width: 145px;
                height: 145px;
            }

            .subscription-note {
                text-align: center;
            }

            .link-box {
                display: flex;
                align-items: center;
                overflow-x: auto;
                overflow-y: hidden;
                white-space: nowrap;
                text-overflow: clip;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }

            .link-box::-webkit-scrollbar {
                display: none;
            }

            .account-strip {
                padding: 10px;
            }

            .username-value {
                max-width: 205px;
                font-size: 11.5px;
            }

            .stats-grid {
                gap: 7px;
            }

            .stats-grid .stat-box:last-child {
                grid-column: 1 / -1;
            }

            .stat-box {
                min-height: 78px;
                padding: 10px 6px;
            }

            .stat-label {
                font-size: 10.5px;
            }

            .stat-value {
                font-size: 12.5px;
            }

            .tutorial-grid {
                gap: 8px;
            }

            .tutorial-item {
                min-height: 98px;
            }

            .modal-backdrop {
                padding: 10px;
                align-items: flex-start;
            }

            .modal-outer {
                margin-top: 20px;
                margin-bottom: 20px;
            }

            .create-modal {
                padding: 15px 11px 13px;
                border-radius: 15px;
            }

            .final-result1 {
                padding: 12px;
                border-radius: 13px;
            }

            .app-summary {
                align-items: stretch;
                flex-direction: column;
            }

            .btn-mini {
                width: 100%;
            }

            .guide-step a {
                font-size: 9.5px;
            }
        }

        @media (max-width: 360px) {
            .username-value {
                max-width: 150px;
            }

            .tutorial-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<canvas id="network-canvas"></canvas>

<div class="page">
    <main class="container">

        <section class="card">
            <div class="card-header">
                <svg class="card-header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19V9"></path>
                    <path d="M10 19V5"></path>
                    <path d="M16 19v-7"></path>
                    <path d="M22 19H2"></path>
                </svg>
                <span class="card-header-title">وضعیت اشتراک</span>
            </div>

            <div class="card-body">
                <div class="account-strip">
                    <div class="username-box">
                        <span class="username-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="4"></circle>
                                <path d="M4.5 21a7.5 7.5 0 0 1 15 0"></path>
                            </svg>
                        </span>
                        <span class="username-text">
                            <span class="username-label">نام کاربری</span>
                            <span class="username-value">{{ $username }}</span>
                        </span>
                    </div>

                    <span class="status-badge {{ $status === 'active' ? 'active' : 'inactive' }}">
                        <span class="status-dot"></span>
                        <span>{{ $status === 'active' ? 'فعال' : 'غیرفعال' }}</span>
                    </span>
                </div>

                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-label">حجم کل</div>
                        <div class="stat-value">{{ $faTraffic($data_limit) }}</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-label">حجم مصرفی</div>
                        <div class="stat-value">{{ $faTraffic($data_used) }}</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-label">حجم باقی‌مانده</div>
                        <div class="stat-value">{{ $faTraffic($data_remaining) }}</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-label">تاریخ انقضا</div>
                        <div class="stat-value">{{ $faNumber($expired_date) }}</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-label">روزهای باقی‌مانده</div>
                        <div class="stat-value">{{ $remainingDays }}</div>
                    </div>
                </div>

                <div class="progress-wrap">
                    <div class="progress-info">
                        <span>میزان مصرف ترافیک</span>
                        <span class="progress-percent">٪{{ $faNumber($usage_percent) }}</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-bar"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <svg class="card-header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 13a5 5 0 0 0 7.07.07l2-2A5 5 0 0 0 12 4l-1.15 1.15"></path>
                    <path d="M14 11a5 5 0 0 0-7.07-.07l-2 2A5 5 0 0 0 12 20l1.15-1.15"></path>
                </svg>
                <span class="card-header-title">لینک اشتراک</span>
            </div>

            <div class="card-body">
                <div class="subscription-layout">
                    <div class="subscription-main">
                        <p class="subscription-note">
                            لینک زیر یا QR CODE را داخل برنامه موردنظر وارد کنید.
                        </p>

                        <div class="link-box" title="{{ $subscription_url }}">
                            {{ $subscription_url }}
                        </div>

                        <button type="button" id="copyButton" class="copy-button" onclick="copySubscription(this)">
                            <svg class="copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="11" height="11" rx="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span class="copy-text">کپی لینک اشتراک</span>
                        </button>
                    </div>

                    <div class="qr-box">
                        <img src="data:image/svg+xml;base64,{{ $qr_code }}" alt="QR Code">
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <svg class="card-header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"></path>
                </svg>
                <span class="card-header-title">راهنمای اتصال</span>
            </div>

            <div class="card-body">
                <div class="tutorial-grid">
                    <button type="button" class="tutorial-item" onclick="openTutorial('ios')">
                        <span class="platform-icon"><svg class="apple-logo" viewBox="0 0 384 512" fill="#f5f5f7" aria-hidden="true" preserveAspectRatio="xMidYMid meet"><path d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5c0 26.2 4.8 53.3 14.4 81.2 12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zM260.6 104.5c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z"/></svg></span>
                        <span class="platform-name">آیفون</span>
                        <span class="platform-app">Streisand</span>
                    </button>

                    <button type="button" class="tutorial-item" onclick="openTutorial('android')">
                        <span class="platform-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><g fill="#3DDC84"><path d="M7.25 6.25 5.7 3.57a.65.65 0 1 1 1.13-.65L8.4 5.64A9.05 9.05 0 0 1 12 4.9c1.28 0 2.5.26 3.6.74l1.57-2.72a.65.65 0 1 1 1.13.65l-1.55 2.68A7.57 7.57 0 0 1 20 12H4a7.57 7.57 0 0 1 3.25-5.75Z"/><path d="M4 13h16v5.25A1.75 1.75 0 0 1 18.25 20H17v2a1 1 0 1 1-2 0v-2H9v2a1 1 0 1 1-2 0v-2H5.75A1.75 1.75 0 0 1 4 18.25V13Z"/></g><circle cx="8.3" cy="9.1" r=".8" fill="#081014"/><circle cx="15.7" cy="9.1" r=".8" fill="#081014"/></svg></span>
                        <span class="platform-name">اندروید</span>
                        <span class="platform-app">SPEEDBOX</span>
                    </button>

                    <button type="button" class="tutorial-item" onclick="openTutorial('windows')">
                        <span class="platform-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#00A4EF" d="M2.5 4.2 10.7 3v8.1H2.5V4.2Zm9.3-1.35L21.5 1.5v9.6h-9.7V2.85ZM2.5 12.1h8.2v8.15L2.5 19.1v-7Zm9.3 0h9.7v9.55l-9.7-1.35v-8.2Z"/></svg></span>
                        <span class="platform-name">ویندوز</span>
                        <span class="platform-app">SPEEDBOX</span>
                    </button>

                </div>
            </div>
        </section>

        <div class="footer">لینک اشتراک خود را هرگز در اختیار دیگران قرار ندهید</div>

    </main>
</div>

<div id="tutorialModal" class="modal-backdrop" onclick="handleBackdropClick(event)" dir="rtl" aria-hidden="true">
    <div class="modal-outer">
        <div class="create-modal" role="dialog" aria-modal="true" aria-labelledby="tutorialModalTitle">
            <div class="modal-header">
                <div class="modal-title">
                    <span id="modalPlatformIcon" class="modal-platform-icon"></span>
                    <span id="tutorialModalTitle">راهنمای اتصال</span>
                </div>
            </div>

            <div class="final-result1">
                <div class="section-block">
                    <div class="app-summary">
                        <div class="app-summary-name">
                            <span id="modalAppIcon" class="app-summary-badge"></span>
                            <span class="app-name-text">
                                <strong id="modalAppName">—</strong>
                                <span id="modalAppHint">برنامه پیشنهادی برای اتصال</span>
                            </span>
                        </div>

                        <button type="button" class="btn-mini" onclick="copySubscription(this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="11" height="11" rx="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span class="copy-text">کپی لینک سابسکرایب</span>
                        </button>
                    </div>
                </div>

                <div class="section-block">
                    <div class="section-head">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 11l3 3L22 4"></path>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                        </svg>
                        <span>مراحل اتصال</span>
                    </div>

                    <ol id="modalSteps" class="guide-steps"></ol>
                </div>

                <div id="speedboxFeaturesBlock" class="section-block" style="display: none;">
                    <div class="section-head">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 2 4.5 13H11l-1 9L19.5 11H13l0-9Z"></path>
                        </svg>
                        <span>ویژگی‌های برنامه SPEEDBOX</span>
                    </div>

                    <ul id="speedboxFeatures" class="speedbox-features"></ul>
                </div>

            </div>

            <div class="modal-back-button">
                <button type="button" onclick="closeTutorial()">بازگشت</button>
            </div>
        </div>
    </div>
</div>

<script>
    const subscriptionUrl = @json($subscription_url);

    const icons = {
        apple: `<svg class="apple-logo" viewBox="0 0 384 512" fill="#f5f5f7" aria-hidden="true" preserveAspectRatio="xMidYMid meet"><path d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5c0 26.2 4.8 53.3 14.4 81.2 12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zM260.6 104.5c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z"/></svg>`,
        android: `<svg viewBox="0 0 24 24" aria-hidden="true"><g fill="#3DDC84"><path d="M7.25 6.25 5.7 3.57a.65.65 0 1 1 1.13-.65L8.4 5.64A9.05 9.05 0 0 1 12 4.9c1.28 0 2.5.26 3.6.74l1.57-2.72a.65.65 0 1 1 1.13.65l-1.55 2.68A7.57 7.57 0 0 1 20 12H4a7.57 7.57 0 0 1 3.25-5.75Z"/><path d="M4 13h16v5.25A1.75 1.75 0 0 1 18.25 20H17v2a1 1 0 1 1-2 0v-2H9v2a1 1 0 1 1-2 0v-2H5.75A1.75 1.75 0 0 1 4 18.25V13Z"/></g><circle cx="8.3" cy="9.1" r=".8" fill="#081014"/><circle cx="15.7" cy="9.1" r=".8" fill="#081014"/></svg>`,
        windows: `<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#00A4EF" d="M2.5 4.2 10.7 3v8.1H2.5V4.2Zm9.3-1.35L21.5 1.5v9.6h-9.7V2.85ZM2.5 12.1h8.2v8.15L2.5 19.1v-7Zm9.3 0h9.7v9.55l-9.7-1.35v-8.2Z"/></svg>`,
        link: `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 13a5 5 0 0 0 7.07.07l2-2A5 5 0 0 0 12 4l-1.15 1.15"></path><path d="M14 11a5 5 0 0 0-7.07-.07l-2 2A5 5 0 0 0 12 20l1.15-1.15"></path>
            </svg>`
    };

    const tutorials = {
        ios: {
            title: 'راهنمای اتصال آیفون',
            app: 'Streisand',
            platformIcon: icons.apple,
            appIcon: icons.apple,
            steps: [
                'ابتدا لینک را کپی کرده و وارد برنامه شوید.',
                'داخل تب Home علامت + بالا سمت راست را بزنید.',
                'گزینه سوم (Import from Clipboar) را بزنید.',
                'گزینه Allow paste را بزنید؛ لیست سرورها پس از چند ثانیه لود خواهد شد.',
                'داخل Setting وارد قسمت Subscription شوید و گزینه Update on open را روشن کنید تا سرورها به صورت اتوماتیک آپدیت شوند.',
                'داخل Setting وارد قسمت tunnel شوید و گزینه IP Settings را روی ip4 قرار دهید.',
                'در صورت لزوم به آپدیت دستی لیست سرورها، داخل صفحه اول (Home) روی اولین و بالاترین گزینه لیست سرورها انگشتتان را نگه دارید؛ یک لیست برای شما ظاهر می‌شود. گزینه update را بزنید تا تغییراتی که روی سرورها انجام شده، اعمال شود.'
            ]
        },
        android: {
            title: 'راهنمای اتصال اندروید',
            app: '(مخصوص گوشی و اندروید تی وی) SPEEDBOX',
            platformIcon: icons.android,
            appIcon: icons.android,
            steps: [
                'برنامه را از لینک زیر دانلود کنید :<br><a href="https://io.subupdatefast.info/Download/speedbox/SPEEDBOX.apk" target="_blank" rel="noopener">https://io.subupdatefast.info/Download/speedbox/SPEEDBOX.apk</a>',
                'لینک اشتراک را داخل برنامه وارد کرده و کشور مورد نظر را انتخاب کرده و دکمه اتصال را بزنید تا به بهترین سرور مورد نظر با توجه به اینترنت خود متصل شوید.',
            ],
            features: [
                'انتخاب خودکار بهترین سرور',
                'باز کردن سایت‌های ایرانی بدون نیاز به خاموش کردن وی پی ان',
                'تماشای یوتیوب بدون تبلیغات',
                'باز کردن سایت‌های نتفلیکس و اسپاتیفای',
                'به اشتراک گذاری وی پی ان با دیگر دستگاهها'
            ]
        },
        windows: {
            title: 'راهنمای اتصال ویندوز',
            app: 'SPEEDBOX',
            platformIcon: icons.windows,
            appIcon: icons.windows,
            steps: [
                'برنامه را از لینک زیر دانلود کنید :<br><a href="https://io.subupdatefast.info/Download/speedbox/SPEEDBOX.exe" target="_blank" rel="noopener">https://io.subupdatefast.info/Download/speedbox/SPEEDBOX.exe</a>',
                'لینک اشتراک را داخل برنامه وارد کرده و کشور مورد نظر را انتخاب کرده و دکمه اتصال را بزنید تا به بهترین سرور مورد نظر با توجه به اینترنت خود متصل شوید.',
            ],
            features: [
                'انتخاب خودکار بهترین سرور',
                'باز کردن سایت‌های ایرانی بدون نیاز به خاموش کردن وی پی ان',
                'تماشای یوتیوب بدون تبلیغات',
                'باز کردن سایت‌های نتفلیکس و اسپاتیفای',
                'به اشتراک گذاری وی پی ان با دیگر دستگاهها'
            ]
        }
    };

    function faNumber(value) {
        return String(value).replace(/[0-9]/g, digit => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
    }

    async function copySubscription(button) {
        const textElement = button ? button.querySelector('.copy-text') : null;
        const originalText = textElement ? textElement.textContent : '';

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(subscriptionUrl);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = subscriptionUrl;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
            }

            if (button) button.classList.add('success');
            if (textElement) textElement.textContent = 'لینک کپی شد';

            setTimeout(() => {
                if (button) button.classList.remove('success');
                if (textElement) textElement.textContent = originalText || 'کپی لینک سابسکرایب';
            }, 1700);
        } catch (error) {
            if (textElement) {
                textElement.textContent = 'کپی انجام نشد';
                setTimeout(() => {
                    textElement.textContent = originalText || 'کپی لینک سابسکرایب';
                }, 1700);
            }
        }
    }

    function openTutorial(platform) {
        const tutorial = tutorials[platform];
        if (!tutorial) return;

        const modal = document.getElementById('tutorialModal');
        const steps = document.getElementById('modalSteps');
        const featuresBlock = document.getElementById('speedboxFeaturesBlock');
        const featuresList = document.getElementById('speedboxFeatures');

        document.getElementById('tutorialModalTitle').textContent = tutorial.title;
        document.getElementById('modalPlatformIcon').innerHTML = tutorial.platformIcon;
        document.getElementById('modalAppIcon').innerHTML = tutorial.appIcon;
        document.getElementById('modalAppName').textContent = tutorial.app;

        steps.innerHTML = tutorial.steps.map((step, index) => `
            <li class="guide-step">
                <span class="step-number">${faNumber(index + 1)}</span>
                <span>${step}</span>
            </li>
        `).join('');

        if (tutorial.features && tutorial.features.length) {
            featuresList.innerHTML = tutorial.features.map(feature => `
                <li class="speedbox-feature-item">${feature}</li>
            `).join('');

            featuresBlock.style.display = 'block';
        } else {
            featuresList.innerHTML = '';
            featuresBlock.style.display = 'none';
        }

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function closeTutorial() {
        const modal = document.getElementById('tutorialModal');
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    function handleBackdropClick(event) {
        if (event.target === event.currentTarget) closeTutorial();
    }

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeTutorial();
    });

    /* همان network canvas پنل، به‌صورت مستقل برای این Blade */
    (function initCanvas() {
        const cvs = document.getElementById('network-canvas');
        if (!cvs) return;

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) return;

        const ctx = cvs.getContext('2d');
        let w = 0;
        let h = 0;
        let particles = [];
        let rafId = null;
        let lastTs = 0;
        const FRAME_MS = 1000 / 30;

        function settings() {
            const mobile = window.innerWidth <= 600;
            return {
                maxDist: 140,
                count: 70,
                speed: mobile ? 0.18 : 0.28
            };
        }

        let opts = settings();

        function resize() {
            w = window.innerWidth;
            h = window.innerHeight;

            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            cvs.width = Math.floor(w * dpr);
            cvs.height = Math.floor(h * dpr);
            cvs.style.width = w + 'px';
            cvs.style.height = h + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }

        class Particle {
            constructor() {
                this.x = Math.random() * w;
                this.y = Math.random() * h;
                this.vx = (Math.random() - 0.5) * opts.speed;
                this.vy = (Math.random() - 0.5) * opts.speed;
            }

            update() {
                this.x += this.vx;
                this.y += this.vy;

                if (this.x < 0 || this.x > w) this.vx *= -1;
                if (this.y < 0 || this.y > h) this.vy *= -1;
            }

            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, 1.6, 0, Math.PI * 2);
                ctx.fillStyle = '#00b894';
                ctx.fill();
            }
        }

        function initParticles() {
            opts = settings();
            particles = [];
            for (let i = 0; i < opts.count; i++) particles.push(new Particle());
        }

        function connect() {
            for (let i = 0; i < particles.length; i++) {
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const dist = Math.hypot(dx, dy);

                    if (dist < opts.maxDist) {
                        ctx.globalAlpha = 1 - dist / opts.maxDist;
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = '#00b894';
                        ctx.stroke();
                    }
                }
            }
            ctx.globalAlpha = 1;
        }

        function loop(ts) {
            if (!lastTs || ts - lastTs > FRAME_MS) {
                ctx.clearRect(0, 0, w, h);
                for (const particle of particles) {
                    particle.update();
                    particle.draw();
                }
                connect();
                lastTs = ts;
            }
            rafId = requestAnimationFrame(loop);
        }

        function handleVisibility() {
            if (document.hidden) {
                if (rafId) {
                    cancelAnimationFrame(rafId);
                    rafId = null;
                }
            } else if (!rafId) {
                rafId = requestAnimationFrame(loop);
            }
        }

        let resizeTimer = null;
        function handleResize() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                resize();
                initParticles();
            }, 120);
        }

        resize();
        initParticles();
        rafId = requestAnimationFrame(loop);

        document.addEventListener('visibilitychange', handleVisibility);
        window.addEventListener('resize', handleResize, { passive: true });
    })();
</script>

</body>
</html>
