<template>
	<router-view />
</template>

<style>
	.app-content-details {
		position: relative;
		flex: 1 1 524px;
	}

	#app-content-wrapper {
		display: flex;
		position: relative;
		align-items: stretch;
		min-height: 100%;
	}

	.app-content-details div #editor-container {
		position: absolute;
		top: 54px;
	}

	#editor-container #editor {
		max-width: 800px;
		margin-left: auto;
		margin-right: auto;
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

	#version-title, #titleform input[type='text'] {
		font-size: 35px;
		border: none;
		color: var(--color-main-text);
		width: 100%;
		height: 40px;
		opacity: 0.8;
	}

	.page-title {
		width: 100%;
	}

	#titleform input[type='text']:disabled {
		background-color: var(--color-main-background);
		color: var(--color-text-lighter);
		margin: 3px 3px 3px 0;
		padding: 7px 6px;
	}

	#action-menu {
		position: absolute;
		right: 0;
	}
</style>
