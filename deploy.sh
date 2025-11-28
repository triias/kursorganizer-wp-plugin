#!/bin/bash

# =============================================================================
# KursOrganizer WordPress Plugin - Deployment Script
# =============================================================================
#
# Dieses Script synchronisiert das Plugin zu Ihrer WordPress-Installation.
# 
# VERWENDUNG:
#   1. Konfigurieren Sie die Variablen unten entsprechend Ihrer Umgebung
#   2. Führen Sie das Script aus: ./deploy.sh
#
# FÜR LOKALE ENTWICKLUNG:
#   - Setzen Sie DEPLOY_METHOD="local"
#   - Geben Sie den Pfad zu Ihrer WordPress-Installation an
#
# FÜR REMOTE SERVER:
#   - Setzen Sie DEPLOY_METHOD="remote"
#   - Konfigurieren Sie SSH-Zugang und Remote-Pfad
#
# =============================================================================

# --- KONFIGURATION -----------------------------------------------------------

# Deploy-Methode: "local" oder "remote"
DEPLOY_METHOD="local"

# LOKAL: Pfad zur WordPress-Installation
# Beispiel für Local by WP Engine: $HOME/Local Sites/meine-seite/app/public/wp-content/plugins/
LOCAL_WP_PLUGINS_PATH="$HOME/Local Sites/YOUR-SITE-NAME/app/public/wp-content/plugins/"

# REMOTE: SSH-Verbindungsdaten
REMOTE_USER="your-username"
REMOTE_HOST="your-server.com"
REMOTE_PATH="/var/www/html/wp-content/plugins/"

# Plugin-Ordnername (normalerweise nicht ändern)
PLUGIN_FOLDER="kursorganizer-wp-plugin"

# Optionale Ausschlüsse (Dateien/Ordner, die nicht synchronisiert werden sollen)
EXCLUDE_PATTERNS=(
    ".git"
    ".gitignore"
    "node_modules"
    ".DS_Store"
    "*.log"
    "deploy.sh"
    ".env"
    "SHORTCODE_GENERATOR.md"
)

# --- ENDE KONFIGURATION ------------------------------------------------------

# Farben für Ausgabe
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Script-Pfad ermitteln
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo ""
echo "=============================================="
echo "  KursOrganizer Plugin Deployment"
echo "=============================================="
echo ""

# Prüfe, ob rsync installiert ist
if ! command -v rsync &> /dev/null; then
    echo -e "${RED}Fehler: rsync ist nicht installiert!${NC}"
    echo "Bitte installieren Sie rsync:"
    echo "  macOS: brew install rsync"
    echo "  Linux: sudo apt-get install rsync"
    exit 1
fi

# Erstelle rsync exclude-Parameter
EXCLUDE_ARGS=""
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
    EXCLUDE_ARGS="$EXCLUDE_ARGS --exclude=$pattern"
done

# Deployment durchführen
if [ "$DEPLOY_METHOD" = "local" ]; then
    echo "Deploying lokal zu: ${LOCAL_WP_PLUGINS_PATH}${PLUGIN_FOLDER}/"
    echo ""
    
    # Prüfe, ob Ziel-Pfad existiert
    if [ ! -d "$LOCAL_WP_PLUGINS_PATH" ]; then
        echo -e "${RED}Fehler: Der Pfad $LOCAL_WP_PLUGINS_PATH existiert nicht!${NC}"
        echo "Bitte passen Sie LOCAL_WP_PLUGINS_PATH in diesem Script an."
        echo ""
        echo -e "${YELLOW}Tipp für Local by WP Engine:${NC}"
        echo "  Der Pfad sollte sein: \$HOME/Local Sites/[Ihr-Site-Name]/app/public/wp-content/plugins/"
        exit 1
    fi
    
    # Erstelle Plugin-Ordner falls nicht vorhanden
    mkdir -p "${LOCAL_WP_PLUGINS_PATH}${PLUGIN_FOLDER}"
    
    # Synchronisiere Dateien
    rsync -av --delete $EXCLUDE_ARGS \
        "$SCRIPT_DIR/" \
        "${LOCAL_WP_PLUGINS_PATH}${PLUGIN_FOLDER}/"
    
    if [ $? -eq 0 ]; then
        echo ""
        echo -e "${GREEN}✓ Deployment erfolgreich!${NC}"
        echo ""
        echo "Plugin wurde synchronisiert nach:"
        echo "  ${LOCAL_WP_PLUGINS_PATH}${PLUGIN_FOLDER}/"
        echo ""
        echo -e "${YELLOW}Nächste Schritte:${NC}"
        echo "  1. Öffnen Sie WordPress Admin in Local"
        echo "  2. Gehen Sie zu Plugins → Installierte Plugins"
        echo "  3. Aktivieren Sie 'KursOrganizer X iFrame' falls nötig"
    else
        echo -e "${RED}✗ Deployment fehlgeschlagen!${NC}"
        exit 1
    fi

elif [ "$DEPLOY_METHOD" = "remote" ]; then
    echo "Deploying zu Remote-Server..."
    echo "Server: ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}${PLUGIN_FOLDER}/"
    echo ""
    
    # Prüfe SSH-Verbindung
    if ! ssh -q -o BatchMode=yes -o ConnectTimeout=5 "${REMOTE_USER}@${REMOTE_HOST}" exit; then
        echo -e "${RED}Fehler: SSH-Verbindung zu ${REMOTE_USER}@${REMOTE_HOST} fehlgeschlagen!${NC}"
        echo "Bitte prüfen Sie Ihre SSH-Konfiguration."
        exit 1
    fi
    
    # Erstelle Plugin-Ordner falls nicht vorhanden
    ssh "${REMOTE_USER}@${REMOTE_HOST}" "mkdir -p ${REMOTE_PATH}${PLUGIN_FOLDER}"
    
    # Synchronisiere Dateien
    rsync -avz --delete $EXCLUDE_ARGS \
        -e ssh \
        "$SCRIPT_DIR/" \
        "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}${PLUGIN_FOLDER}/"
    
    if [ $? -eq 0 ]; then
        echo ""
        echo -e "${GREEN}✓ Deployment erfolgreich!${NC}"
        echo ""
        echo "Plugin wurde synchronisiert zu:"
        echo "  ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}${PLUGIN_FOLDER}/"
    else
        echo -e "${RED}✗ Deployment fehlgeschlagen!${NC}"
        exit 1
    fi

else
    echo -e "${RED}Fehler: Ungültiger DEPLOY_METHOD: $DEPLOY_METHOD${NC}"
    echo "Bitte setzen Sie DEPLOY_METHOD auf 'local' oder 'remote'"
    exit 1
fi

echo ""
echo "=============================================="
echo ""

# Optional: Öffne Browser zur WordPress Admin-Seite
# Uncomment die folgenden Zeilen, um automatisch den Browser zu öffnen:
# if [ "$DEPLOY_METHOD" = "local" ]; then
#     open "http://localhost/wp-admin/plugins.php"
# fi
