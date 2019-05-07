'use strict';

let token = null;
function getToken() {
  if (!token) {
    return fetch(getUrl('/session/token'), {credentials: 'include'}).then(response => {
      if (response.ok) {
        return response.text().then(text => {
          console.log(text);
          return text;
          //token = text;
          return token || text;
        });
      } else  {
        throw new Error('Could not obtain a CSRF token!');
      }
    }).catch(err => { console.log('Failed to make CSRF token request', err) });
  }
  else {
    return Promise.resolve(token);
  }
}

/**
 * @todo make configurable.
 */
export function getUrl(path = '') { return `${window.location.origin}${path}` };

export function getDeepProp(obj, path) {
  return path.split('.').reduce((obj, prop) => {
    return obj && obj.hasOwnProperty(prop) ? obj[prop] : false;
  }, obj)
}

export function getResponseDocument(url, options = {}) {
  return getToken().then(token => {
    options.credentials = 'include';
    const headers = {};
    headers['Accept'] = 'application/vnd.api+json';
    if (token || options.method === 'DELETE') {
      headers['Content-Type'] = 'application/vnd.api+json';
      headers['X-CSRF-Token'] = token;
    }
    options.headers = headers;
    return fetch(url, options).then(response => {
      return response.status !== 204 ? response.json() : null;
    }).then(doc => {
      return doc;
    }).catch(console.log);
  });
}
