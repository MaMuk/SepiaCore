/**
 * Icon set prefix mappings
 * Maps icon set prefixes to their directory names in /public/icons/
 */
const ICON_SET_MAP = {
  'bi-': 'bootstrap-icons',
  'fa-': 'font-awesome',
  // Add more icon sets as needed
  // 'md-': 'material-design',
  // 'hero-': 'heroicons',
}

/**
 * Default icon set to use when no prefix is detected
 */
const DEFAULT_ICON_SET = 'bootstrap-icons'

/**
 * Default icon name to use when no icon is specified
 */
const DEFAULT_ICON = 'bi-box'

/**
 * Gets the icon path for a given icon name
 * 
 * @param {string} iconName - The icon name (e.g., 'bi-person-vcard-fill', 'fa-contact', 'person-vcard-fill')
 * @returns {string} The full path to the icon SVG file
 * 
 * @example
 * getIconPath('bi-person-vcard-fill') // returns '/icons/bootstrap-icons/person-vcard-fill.svg'
 * getIconPath('fa-contact') // returns '/icons/font-awesome/contact.svg'
 * getIconPath('person-vcard-fill') // returns '/icons/bootstrap-icons/person-vcard-fill.svg' (default)
 */
export function getIconPath(iconName) {
  if (!iconName) {
    // If no icon name provided, use default
    iconName = DEFAULT_ICON
  }

  // Remove .svg extension if present
  const cleanIconName = iconName.replace(/\.svg$/, '')

  // Find matching icon set prefix
  for (const [prefix, directory] of Object.entries(ICON_SET_MAP)) {
    if (cleanIconName.startsWith(prefix)) {
      // Remove prefix and return path
      const iconFileName = cleanIconName.substring(prefix.length)
      return `/icons/${directory}/${iconFileName}.svg`
    }
  }

  // No prefix found, use default icon set
  return `/icons/${DEFAULT_ICON_SET}/${cleanIconName}.svg`
}

/**
 * Gets the icon name without the prefix
 * 
 * @param {string} iconName - The icon name (e.g., 'bi-person-vcard-fill', 'fa-contact')
 * @returns {string} The icon name without the prefix
 * 
 * @example
 * getIconNameWithoutPrefix('bi-person-vcard-fill') // returns 'person-vcard-fill'
 * getIconNameWithoutPrefix('fa-contact') // returns 'contact'
 */
export function getIconNameWithoutPrefix(iconName) {
  if (!iconName) {
    return null
  }

  const cleanIconName = iconName.replace(/\.svg$/, '')

  // Find matching icon set prefix
  for (const prefix of Object.keys(ICON_SET_MAP)) {
    if (cleanIconName.startsWith(prefix)) {
      return cleanIconName.substring(prefix.length)
    }
  }

  // No prefix found, return as is
  return cleanIconName
}

/**
 * Gets the icon set directory name for a given icon name
 * 
 * @param {string} iconName - The icon name (e.g., 'bi-person-vcard-fill', 'fa-contact')
 * @returns {string} The icon set directory name
 * 
 * @example
 * getIconSetDirectory('bi-person-vcard-fill') // returns 'bootstrap-icons'
 * getIconSetDirectory('fa-contact') // returns 'font-awesome'
 */
export function getIconSetDirectory(iconName) {
  if (!iconName) {
    return DEFAULT_ICON_SET
  }

  const cleanIconName = iconName.replace(/\.svg$/, '')

  // Find matching icon set prefix
  for (const [prefix, directory] of Object.entries(ICON_SET_MAP)) {
    if (cleanIconName.startsWith(prefix)) {
      return directory
    }
  }

  // No prefix found, return default
  return DEFAULT_ICON_SET
}

