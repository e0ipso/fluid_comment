'use strict';
import React from 'react';
import { getDeepProp, getResponseDocument } from './functions';
import { getRelUri, objectHasLinkWithRel } from './routes';
import FluidComment from './FluidComment';
import FluidCommentForm from './FluidCommentForm';
import InlineLoginForm from "./InlineLoginForm";

class FluidCommentWrapper extends React.Component {

  constructor(props) {
    super(props);
    this.state = {
      loggedIn: this.props.loginUrl ? false : null,
      comments: [],
      isRefreshing: false
    };
  }

  componentDidMount() {
    this.refreshComments();
  }

  onLogin = (success) => {
    this.setState({loggedIn: !!success});
    this.refreshComments();
  };

  render() {
    const { currentNode, loginUrl, commentType, threaded } = this.props;
    const { comments, loggedIn, isRefreshing } = this.state;
    const addLink = getRelUri('add');
    const hasLink = objectHasLinkWithRel(currentNode, 'comments', addLink);

    return (
      <div>
      {threaded ? this.renderThreaded(comments) : this.renderFlat(comments)}

      {loggedIn === false
        ? <div>
            <h2 className="title comment-form__title">Log in to comment</h2>
            <InlineLoginForm
              key="loginForm"
              loginUrl={loginUrl}
              onLogin={this.onLogin}
            />
          </div>
        : hasLink && <div>
            <h2 className="title comment-form__title">Add new comment</h2>
            <FluidCommentForm
              key="commentForm"
              commentTarget={currentNode}
              commentType={commentType}
              onSubmit={() => this.refreshComments()}
              isRefreshing={isRefreshing}
            />
          </div>
      }
      </div>
    );
  }

  renderThreaded(comments) {
    const { isRefreshing } = this.state;
    const rendered = [];
    let stack = [];
    const destack = (stack, current) => {
      let item, last = null;
      while (item = stack.pop()) {
        if (last !== null) {
          item.children.push(last);
        }
        if (!current || !current.parentId || current.parentId !== item.id) {
          last = item.render(item.children);
        }
        else {
          stack.push(item);
          break;
        }
      }
      if (!current || !current.parentId) {
        rendered.push(last);
      }
      return stack;
    }
    for (var i = 0; i < comments.length; i++) {
      const comment = comments[i];
      const current = {
        id: comments[i].id,
        parentId: getDeepProp(comments[i], 'relationships.pid.data.id'),
        render: (children) => {
          return (
              <FluidComment
                  key={comment.id}
                  index={i}
                  comment={comment}
                  refresh={() => this.refreshComments()}
                  isRefreshing={isRefreshing}
              >
                {children}
              </FluidComment>
          );
        },
        children: [],
      };
      if (i > 0)  {
        stack = destack(stack, current);
      }
      stack.push(current);
    }
    destack(stack);
    return rendered;
  }

  renderFlat(comments) {
    const { isRefreshing } = this.state;
    return comments.map((comment, index) => (
      <FluidComment
          key={comment.id}
          index={index}
          comment={comment}
          refresh={() => this.refreshComments()}
          isRefreshing={isRefreshing}
      />
    ));
  }

  refreshComments() {
    if (objectHasLinkWithRel(this.props.currentNode, 'comments', getRelUri('collection'))) {
      this.getAndAddComments(`${this.props.currentNode.links.comments.href}/?include=uid.user_picture`);
    }
  }

  /**
   * @todo Replace with serializing
   */
  mergeIncluded(comments, included) {
    return comments.map(comment => {

      const { uid } = comment.relationships;
      const users = included.filter(item => item.id === uid.data.id);

      if (users.length > 0) {
        let user = users[0];
        const pic = getDeepProp(user, 'relationships.user_picture.data');
        if (pic) {
          const pictures = included.filter(item => item.id === pic.id);
          if (pictures.length > 0) {
            user = Object.assign(user, { picture: pictures[0] });
          }
        }

        return Object.assign(comment, { user });
      }

      return comment;
    })
  }

  getAndAddComments(commentsUrl, previous = []) {
    this.setState({ isRefreshing: true });

    getResponseDocument(commentsUrl).then(doc => {
      const data = getDeepProp(doc, 'data');
      const included = getDeepProp(doc, 'included');
      const nextUrl = getDeepProp(doc, 'links.next.href');

      const comments = [...previous, ...this.mergeIncluded(data, included)];

      if (nextUrl) {
        this.getAndAddComments(nextUrl, comments);
      }

      this.setState({ comments, isRefreshing: false });
    });
  }

}

export default FluidCommentWrapper;
