import axios from '@nextcloud/axios'
import { createClient } from '../../client/client/client.gen.ts'

export const client = createClient({ axios })
export const headers = { 'OCS-APIRequest': true }
export const path = Object.freeze({ apiVersion: '1.0' })
export const defaultOptions = Object.freeze({
	client,
	headers,
	path,
	throwOnError: true,
})
