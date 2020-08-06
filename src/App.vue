<template>
	<router-view />
</template>

<style>
	.app-content-details {
		position: relative;
		flex: 1 1 524px;
	}

	#app-content-wrapper {
		padding-top: 40px;
		display: flex;
		position: relative;
		align-items: stretch;
		min-height: 100%;
	}

	.app-content-details div #editor-container {
		position: absolute;
	}

	#editor-container #editor .menubar {
		z-index: 100;
	}

	#editor-wrapper #editor div.ProseMirror {
		margin-top: 5px;
	}

	#text-wrapper #text div.ProseMirror {
		margin-top: 5px;
	}

	.page-title, #titleform input[type="text"] {
		font-size: 35px;
		border: none;
		font-weight: 600;
		color: var(--color-main-text);
		width: 100%;
		height: 40px;
		opacity: 0.8;
	}

	.page-title, #titleform input[type="text"]:focus {
		opacity: 1;
	}

	#action-menu {
		position: absolute;
		right: 0;
	}
</style>
