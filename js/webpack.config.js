const path = require('path');

const config = {
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader"
        }
      }
    ],
  },
  output: {
    filename: 'fluid-comment.js',
    path: path.resolve(__dirname, 'dist')
  },
  devtool: 'source-map'
};

module.exports = (env, argv) => {
  return config;
};
