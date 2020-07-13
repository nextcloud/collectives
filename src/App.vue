<template>
	<router-view />
</template>

<style>
	.app-content-details {
		position: relative
	}

	#app-content-wrapper {
		padding-top: 40px
	}

	.app-content-details div #editor-container {
		position: absolute
	}

	.page-title, #titleform input[type="text"] {
		font-size: 36px;
		border: none;
		font-weight: 600;
		color: var(--color-main-text);
		width: 100%;
	}

	#action-menu {
		position: absolute;
		right: 0;
	}
</style>
