// Circle lember levels
export const memberLevels = {
	LEVEL_MEMBER: 1,
	LEVEL_MODERATOR: 4,
	LEVEL_ADMIN: 8,
	LEVEL_OWNER: 9,
}

// Circle member types
export const circlesMemberTypes = {
	TYPE_USER: 1,
	TYPE_GROUP: 2,
	TYPE_MAIL: 4,
	TYPE_CIRCLE: 16,
}

export const autocompleteSourcesToCircleMemberTypes = {
	users: 'TYPE_USER',
	groups: 'TYPE_GROUP',
	circles: 'TYPE_CIRCLE',
}

// Nextcloud share types
export const shareTypes = {
	TYPE_USER: 0,
	TYPE_GROUP: 1,
	TYPE_EMAIL: 4,
	TYPE_REMOTE: 6,
	TYPE_CIRCLE: 7,
}

// Page modes
export const pageModes = {
	MODE_VIEW: 0,
	MODE_EDIT: 1,
}

export const editorApiReaderFileId = 'READER_FILE_ID'

export const sessionUpdateInterval = 90 // in seconds
