<template>
	<router-view />
</template>

<style>
	#app-content > div {
		width: 100%;
		height: 100%;
		padding: 20px;
		display: flex;
		flex-direction: column;
		flex-grow: 1;
	}

	.page-title, #titleform input[type="text"] {
		font-size: 36px;
		border: none;
		font-weight: 600;
		color: var(--color-main-text);
		width: 100%
	}

	#action-menu {
		position: absolute;
		right: 0;
	}
</style>
