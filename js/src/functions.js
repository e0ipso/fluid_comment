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

export function getFormKey(prefix = 'commentForm') {
  return `${prefix}-${+new Date()}`;
}

export function getDeepProp(obj, path) {
  return path.split('.').reduce((obj, prop) => {
    return obj && obj.hasOwnProperty(prop) ? obj[prop] : false;
  }, obj)
}

export function getResponseDocument(url, options = {}) {
  return getToken().then(token => {
    const headers = {};

    headers['Accept'] = 'application/vnd.api+json';
    if (token || options.method === 'DELETE') {
      headers['Content-Type'] = 'application/vnd.api+json';
      headers['X-CSRF-Token'] = token;
    }

    options.headers = headers;
    options.credentials = 'include';

    return fetch(url, options)
      .then(response => (response.status !== 204
        ? response.json()
        : null))
      .then(doc => {

      if (doc.errors) {
        doc.errors.forEach(error => {
          const { status, detail } = error;
          console.log('error', { url, status, detail });
        });
      }

      return doc;
    }).catch(e => {
      console.log('error', { url, e });
    });
  });
}

export function formatBodyAdd(values, node, field, type) {
  const { subjectField, bodyField, bodyFormat } = values;

  const attributes = {
    subject: subjectField,
    comment_body: {
      value: bodyField,
      format: bodyFormat,
    },
    field_name: field,
    entity_type: 'node',
  };

  const relationships = {
    entity_id: {
      data: {
        type: getDeepProp(node, 'type'),
        id: getDeepProp(node, 'id'),
      }
    }
  };

  return JSON.stringify({ data: { attributes, relationships, type } });
}

export function formatBodyUpdate(values, id, type) {
  const { subjectField, bodyField } = values;

  const attributes = {
    subject: subjectField,
    comment_body: {
      value: bodyField,
      format: 'restricted_html',
    },
  };

  return JSON.stringify({ data: { id, attributes, type } });
}
