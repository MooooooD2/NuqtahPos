use tauri::Manager;

// ─── Custom Tauri commands ────────────────────────────────────────────────────

/// Print a receipt via OS native print dialog
#[tauri::command]
async fn print_receipt(content: String) -> Result<String, String> {
    // In production: integrate with thermal printer via serial/USB
    // For now we return the content for the webview to handle via CSS @media print
    Ok(format!("Receipt queued: {} bytes", content.len()))
}

/// Open a cash drawer connected via serial port
#[tauri::command]
async fn open_cash_drawer() -> Result<(), String> {
    // ESC/POS command to open cash drawer: 0x1B 0x70 0x00 0x19 0xFA
    // Integrate with serialport crate in production
    println!("Cash drawer open command sent");
    Ok(())
}

/// Get system info (CPU, memory) for health check display
#[tauri::command]
async fn get_system_info() -> serde_json::Value {
    serde_json::json!({
        "os": std::env::consts::OS,
        "arch": std::env::consts::ARCH,
        "app_version": env!("CARGO_PKG_VERSION"),
    })
}

/// Stable per-machine identifier used to bind a license key to this device.
#[tauri::command]
async fn get_device_id() -> Result<String, String> {
    machine_uid::get().map_err(|e| e.to_string())
}

// ─── App setup ────────────────────────────────────────────────────────────────

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_fs::init())
        .plugin(tauri_plugin_http::init())
        .plugin(tauri_plugin_notification::init())
        .plugin(tauri_plugin_store::Builder::default().build())
        .plugin(
            tauri_plugin_sql::Builder::default()
                .add_migrations("sqlite:pos_local.db", vec![
                    tauri_plugin_sql::Migration {
                        version: 1,
                        description: "create offline tables",
                        sql: include_str!("../migrations/001_initial.sql"),
                        kind: tauri_plugin_sql::MigrationKind::Up,
                    },
                ])
                .build(),
        )
        .setup(|app| {
            // Set window title
            if let Some(window) = app.get_webview_window("main") {
                let _ = window.set_title("POS Enterprise");
            }
            Ok(())
        })
        .invoke_handler(tauri::generate_handler![
            print_receipt,
            open_cash_drawer,
            get_system_info,
            get_device_id,
        ])
        .run(tauri::generate_context!())
        .expect("error while running Tauri application");
}
