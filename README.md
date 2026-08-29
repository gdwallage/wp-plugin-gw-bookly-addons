# GW Bookly Addons

Custom Bookly extensions and integrations for Gary Wallage Photography WordPress Multisite.

## Features
- **Compound Service Addons**: Extended data handling and dynamic UI controls for Bookly Pro services.
- **Custom Schema Management**: Direct database hooks for custom service images, extra fields, and consultation mappings.
- **AJAX & Admin Interfaces**: Service edit dialog extensions with live previews and dynamic option saving.
- **Multisite Compatibility**: Built to operate across all photography genre sites within the network.

## Architecture
- `main.php`: Plugin entry point and asset enqueueing.
- `autoload.php`: Class loader for plugin namespace.
- `lib/`: Core plugin classes (Ajax, Blocks, Boot, Installer, Utils).
- `backend/`: Admin dialogs, components, and templates.
