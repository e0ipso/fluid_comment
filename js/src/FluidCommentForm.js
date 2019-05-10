'use strict';

import React from 'react';
import { getDeepProp, getResponseDocument } from "./functions";

class FluidCommentForm extends React.Component {

  constructor(props) {
    super(props);
    this.state = {
      subjectField: '',
      bodyField: ''
    };
    this.handleChange = this.handleChange.bind(this);
    this.handleSubmit = this.handleSubmit.bind(this);
  }

  render() {
    const { isRefreshing } = this.props;
    const { subjectField, bodyField } = this.state;

    return (
      <div>
        <form className="comment-comment-form comment-form" onSubmit={this.handleSubmit}>
          <div className="form-item">
            <label for="subjectField">Subject</label>
            <input
              type="text"
              name="subjectField"
              className="form-text"
              value={subjectField}
              onChange={this.handleChange}
            />
          </div>
          <div className="text-format-wrapper form-item">
            <div className="form-type-textarea form-item">
              <label for="bodyField" className="form-required">Body</label>
              <div className="form-textarea-wrapper">
                <textarea
                  type="text"
                  name="bodyField"
                  className="form-textarea required resize-vertical"
                  data-editor-active-text-format="basic_html"
                  value={bodyField}
                  onChange={this.handleChange}
                />
            </div>
          </div>
          </div>
          <div className="form-actions">
            <input
              className={`button ${isRefreshing && 'is-disabled'}`}
              type="submit"
              value="Post Comment"
              disabled={isRefreshing}
            />
          </div>
        </form>
      </div>
    );
  }

  handleChange(event) {
    this.setState({[event.target.name]: event.target.value});
  }

  handleSubmit(event) {
    event.preventDefault();
    const requestDocument = {
      data: {
        type: this.props.commentType,
        attributes: {
          subject: this.state.subjectField,
          comment_body: {
            value: this.state.bodyField,
            format: 'restricted_html',
          },
          field_name: 'comment',
          entity_type: 'node',
        },
        relationships: {
          entity_id: {
            data: {
              type: getDeepProp(this.props.commentTarget, 'type'),
              id: getDeepProp(this.props.commentTarget, 'id'),
            }
          }
        }
      }
    };
    if (this.props.currentUserId) {
      requestDocument['data']['relationships']['uid'] = {
        data: {
          type: 'user--user',
          id: this.props.currentUserId,
        }
      }
    }
    const options = {
      method: 'POST',
      body: JSON.stringify(requestDocument),
    };
    getResponseDocument(this.props.commentsUrl, options).then(doc => {
      this.props.onSubmit();
      this.setState({ subjectField: '', bodyField: '' });
    });
  }

}

export default FluidCommentForm;
