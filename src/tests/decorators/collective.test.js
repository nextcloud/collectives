import decorate from '../../decorators/collective'

test('name without emoji', () => {
	const decorated = decorate({
		id: 'id',
		name: 'name',
		circleUniqueId: 123,
	})
	expect(decorated.emoji).toBe('')
})

test('name with digit', () => {
	const decorated = decorate({
		id: 'id',
		name: 'name 1',
		circleUniqueId: 123,
	})
	expect(decorated.emoji).toBe('')
	expect(decorated.title).toBe('name 1')
})

test('name with emoji', () => {
	const decorated = decorate({
		id: 'id',
		name: 'name 😜',
		circleUniqueId: 123,
	})
	expect(decorated.emoji).toBe('😜')
	expect(decorated.title).toBe('name')
})

test('name with joined utf-8 emoji', () => {
	const decorated = decorate({
		id: 'id',
		name: '道 Ω🚵‍♂️',
		circleUniqueId: 123,
	})
	expect(decorated.emoji).toBe('🚵‍♂️')
	expect(decorated.title).toBe('道 Ω')
})

test('name with multiple joined utf-8 emoji', () => {
	const decorated = decorate({
		id: 'id',
		name: '道 👩‍❤️‍👩',
		circleUniqueId: 123,
	})
	expect(decorated.emoji).toBe('👩‍❤️‍👩')
	expect(decorated.title).toBe('道')
})

test('name with non-emoji utf-8', () => {
	const decorated = decorate({
		id: 'id',
		name: 'دوجو',
		circleUniqueId: 123,
	})
	expect(decorated.emoji).toBe('')
	expect(decorated.title).toBe('دوجو')
})
