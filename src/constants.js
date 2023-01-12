import { translate as t } from '@nextcloud/l10n'

// Circle lember levels
export const memberLevels = {
	LEVEL_MEMBER: 1,
	LEVEL_MODERATOR: 4,
	LEVEL_ADMIN: 8,
	LEVEL_OWNER: 9,
}

// Share types
export const shareTypes = {
	TYPE_USER: 0,
	TYPE_GROUP: 1,
	TYPE_EMAIL: 4,
	TYPE_REMOTE: 6,
	TYPE_CIRCLE: 7,
}

// Member picker types
export const pickerTypeGrouping = [
	{
		id: `picker-${shareTypes.TYPE_USER}`,
		label: t('collectives', 'Users'),
		share: shareTypes.TYPE_USER,
		type: 1,
	},
	{
		id: `picker-${shareTypes.TYPE_GROUP}`,
		label: t('collectives', 'Groups'),
		share: shareTypes.TYPE_GROUP,
		type: 2,
	},
	{
		id: `picker-${shareTypes.TYPE_EMAIL}`,
		label: t('collectives', 'Email addresses'),
		share: shareTypes.TYPE_EMAIL,
		type: 4,
	},
	{
		id: `picker-${shareTypes.TYPE_CIRCLE}`,
		label: t('collectives', 'Circles'),
		share: shareTypes.TYPE_CIRCLE,
		type: 16,
	},
]

// Page modes
export const pageModes = {
	MODE_VIEW: 0,
	MODE_EDIT: 1,
}
