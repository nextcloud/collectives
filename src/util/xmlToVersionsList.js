import moment from '@nextcloud/moment'
import { generateRemoteUrl } from '@nextcloud/router'
import { formatFileSize } from '@nextcloud/files'

/**
 * Extract list of versions from an XML-encoded Nextcloud DAV API response
 * @param {string} xml XML string
 * @returns {array|null}
 */
export default function(xml) {
	const json = xmlToJson(parseXml(xml))
	const list = json['d:multistatus']['d:response']
	const result = []
	for (const index in list) {
		const version = list[index]['d:propstat']
		if (!version || version['d:status']['#text'] !== 'HTTP/1.1 200 OK') {
			continue
		}
		const url = list[index]['d:href']['#text']
		const time = moment.unix(url.split('/').pop())
		const size = version['d:prop']['d:getcontentlength']['#text']
		result.push({
			downloadUrl: generateRemoteUrl(url.split('remote.php/', 2)[1]),
			formattedTimestamp: time.format('LLL'),
			relativeTimestamp: time.fromNow(),
			timestamp: time.unix(),
			millisecondsTimestamp: time.valueOf(),
			humanReadableSize: formatFileSize(size),
			altSize: n('files', '%n byte', '%n bytes', size),
		})
	}
	return result
}

/**
 * Convert an XML DOM object into a JSON object
 * Copied from apps/workflowengine/src/components/Checks/MultiselectTag/api.js
 * @param {object} xml XML object
 * @returns {object}
 */
function xmlToJson(xml) {
	let obj = {}
	if (xml.nodeType === 1) {
		if (xml.attributes.length > 0) {
			obj['@attributes'] = {}
			for (let j = 0; j < xml.attributes.length; j++) {
				const attribute = xml.attributes.item(j)
				obj['@attributes'][attribute.nodeName] = attribute.nodeValue
			}
		}
	} else if (xml.nodeType === 3) {
		obj = xml.nodeValue
	}
	if (xml.hasChildNodes()) {
		for (let i = 0; i < xml.childNodes.length; i++) {
			const item = xml.childNodes.item(i)
			const nodeName = item.nodeName
			if (typeof (obj[nodeName]) === 'undefined') {
				obj[nodeName] = xmlToJson(item)
			} else {
				if (typeof obj[nodeName].push === 'undefined') {
					const old = obj[nodeName]
					obj[nodeName] = []
					obj[nodeName].push(old)
				}
				obj[nodeName].push(xmlToJson(item))
			}
		}
	}
	return obj
}

/**
 * Read string with XML content into DOMParser()
 * Copied from apps/workflowengine/src/components/Checks/MultiselectTag/api.js
 * @param {string} xml XML string
 * @returns {object|null}
 */
function parseXml(xml) {
	let dom = null
	try {
		dom = (new DOMParser()).parseFromString(xml, 'text/xml')
	} catch (e) {
		console.error('Failed to parse xml document', e)
	}
	return dom
}
